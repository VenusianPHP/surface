<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\DrawingException;
use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Drawing\StrokeStyle;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\FillRule;
use Surface\Contracts\Rasterize\RasterizeDriver;
use Surface\Contracts\Rasterize\StrokeCap;
use Surface\Contracts\Rasterize\StrokeJoin;
use Surface\Drawing\Velvet\VelvetGE;
use Surface\Framebuffers\Native\NativeFramebufferDriver;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\Path;

/*
 * fill(Path) and stroke(Path, StrokeStyle) lower to the existing 'path' command: the path goes through the
 * transform, is flattened within a quarter pixel, and is filled (or its stroke outline is) NON_ZERO.
 */

function square(float $x, float $y, float $side): Path
{
    return (new Path())->moveTo($x, $y)->lineTo($x + $side, $y)->lineTo($x + $side, $y + $side)->lineTo($x, $y + $side)->close();
}

function ink(): Color
{
    return Color::rgb(255, 0, 0);
}

it('fills a path as the polygon fillPolygon gives, through the transform', function (): void {
    $filled = (new RecordingEngine())->commandsOf(fn (RenderingEngine $g) => $g->translate(5, 6)->scale(2)->fill(square(1, 1, 3), ink()));
    $polygon = (new RecordingEngine())->commandsOf(fn (RenderingEngine $g) => $g->translate(5, 6)->scale(2)->fillPolygon([[1, 1], [4, 1], [4, 4], [1, 4]], ink()));

    expect($filled)->toEqual($polygon)
        ->and($filled[0][0])->toBe('path')
        ->and($filled[0][1])->toBe([[[7.0, 8.0], [13.0, 8.0], [13.0, 14.0], [7.0, 14.0]]]);
});

it('closes an open subpath for the fill, fills every subpath, and takes the rule', function (): void {
    $open = (new Path())->moveTo(0, 0)->lineTo(10, 0)->lineTo(10, 10);
    $two = square(0, 0, 4)->moveTo(10, 10)->lineTo(20, 10)->lineTo(20, 20);
    $commands = (new RecordingEngine())->commandsOf(function (RenderingEngine $g) use ($open, $two): void {
        $g->fill($open, ink(), FillRule::EVEN_ODD);
        $g->fill($two, ink());
    });

    expect($commands[0][1])->toHaveCount(1)
        ->and($commands[0][2])->toBe(FillRule::EVEN_ODD)
        ->and($commands[1][1])->toHaveCount(2);
});

it('fills a curve as a polygon that follows it within a quarter pixel', function (): void {
    $path = (new Path())->moveTo(0, 0)->cubicTo(0, 40, 40, 40, 40, 0)->close();
    $commands = (new RecordingEngine())->commandsOf(fn (RenderingEngine $g) => $g->fill($path, ink()));
    $points = $commands[0][1][0];

    expect(count($points))->toBeGreaterThan(8)
        ->and($points[0])->toBe([0.0, 0.0]);
});

it('draws nothing for a path with no area, and for an empty path', function (): void {
    $line = (new Path())->moveTo(0, 0)->lineTo(10, 0);

    expect((new RecordingEngine())->commandsOf(fn (RenderingEngine $g) => $g->fill($line, ink())))->toBe([])
        ->and((new RecordingEngine())->commandsOf(fn (RenderingEngine $g) => $g->fill(new Path(), ink())))->toBe([])
        ->and((new RecordingEngine())->commandsOf(fn (RenderingEngine $g) => $g->stroke(new Path(), ink(), new StrokeStyle())))->toBe([]);
});

it('strokes a path as a NON_ZERO fill of its outline, the width scaled by a uniform transform', function (): void {
    $line = (new Path())->moveTo(0, 0)->lineTo(10, 0);
    $plain = (new RecordingEngine())->commandsOf(fn (RenderingEngine $g) => $g->stroke($line, ink(), new StrokeStyle(width: 4.0)));
    $scaled = (new RecordingEngine())->commandsOf(fn (RenderingEngine $g) => $g->scale(2)->stroke($line, ink(), new StrokeStyle(width: 4.0)));
    $turned = (new RecordingEngine())->commandsOf(fn (RenderingEngine $g) => $g->translate(30, 30)->rotate(M_PI / 2)->stroke($line, ink(), new StrokeStyle(width: 4.0)));

    $box = fn (array $contours): array => [min(array_column($contours[0], 0)), min(array_column($contours[0], 1)), max(array_column($contours[0], 0)), max(array_column($contours[0], 1))];

    expect($plain[0][0])->toBe('path')
        ->and($plain[0][2])->toBe(FillRule::NON_ZERO)
        ->and($plain[0][1])->toHaveCount(1)
        ->and($box($plain[0][1]))->toBe([0.0, -2.0, 10.0, 2.0])
        ->and($box($scaled[0][1]))->toBe([0.0, -4.0, 20.0, 4.0]);
    $xs = array_column($turned[0][1][0], 0);
    $ys = array_column($turned[0][1][0], 1);
    expect([min($xs), max($xs)])->toEqualWithDelta([28.0, 32.0], 1e-9)
        ->and([min($ys), max($ys)])->toEqualWithDelta([30.0, 40.0], 1e-9);
});

it('puts a dash into pieces, each with its own outline', function (): void {
    $line = (new Path())->moveTo(0, 0)->lineTo(10, 0);
    $commands = (new RecordingEngine())->commandsOf(fn (RenderingEngine $g) => $g->stroke($line, ink(), new StrokeStyle(width: 2.0, dash: [2.0, 3.0])));

    expect($commands[0][1])->toHaveCount(2);
});

it('scales the dash with the transform', function (): void {
    $line = (new Path())->moveTo(0, 0)->lineTo(10, 0);
    $commands = (new RecordingEngine())->commandsOf(fn (RenderingEngine $g) => $g->scale(2)->stroke($line, ink(), new StrokeStyle(width: 1.0, dash: [2.0, 3.0])));
    $firstDash = $commands[0][1][0];

    expect(max(array_column($firstDash, 0)))->toBe(4.0);
});

it('refuses a stroke under a transform that scales x and y apart or shears', function (Closure $transform): void {
    $line = (new Path())->moveTo(0, 0)->lineTo(10, 0);

    expect(fn () => (new RecordingEngine())->commandsOf(function (RenderingEngine $g) use ($transform, $line): void {
        $transform($g);
        $g->stroke($line, ink(), new StrokeStyle());
    }))->toThrow(DrawingException::class, 'scales x and y alike');
})->with([
    'an uneven scale' => [fn (RenderingEngine $g) => $g->scale(2, 1)],
    'a shear' => [fn (RenderingEngine $g) => $g->transform(new Surface\NutsAndBolts\Affine(1.0, 0.0, 0.5, 1.0, 0.0, 0.0))],
]);

it('refuses a point past the limit of the surface', function (): void {
    expect(fn () => (new RecordingEngine())->commandsOf(fn (RenderingEngine $g) => $g->fill(square(0, 0, 1.0e12), ink())))->toThrow(DrawingException::class);
});

it('refuses a stroke style that is not one', function (Closure $make): void {
    expect($make)->toThrow(DrawingException::class);
})->with([
    'a negative width' => [fn () => new StrokeStyle(width: -1.0)],
    'a miter limit under 1' => [fn () => new StrokeStyle(miterLimit: 0.5)],
    'a negative dash' => [fn () => new StrokeStyle(dash: [1.0, -1.0])],
    'an all-zero dash' => [fn () => new StrokeStyle(dash: [0.0, 0.0])],
    'a dash that is not a list' => [fn () => new StrokeStyle(dash: ['a' => 1.0])],
    'a width that is not finite' => [fn () => new StrokeStyle(width: NAN)],
]);

function paintOf(RasterizeDriver $raster, Closure $draw, int $width = 24, int $height = 16): Surface\Contracts\Framebuffers\Framebuffer
{
    $buffer = (new NativeFramebufferDriver())->full(FormatSpec::rgba8(), $width, $height);
    (new VelvetGE($buffer, $raster, Edges::HARD))->frame($draw);

    return $buffer;
}

it('draws a filled path as the same pixels as the rect it describes', function (RasterizeDriver $raster): void {
    $filled = paintOf($raster, fn (RenderingEngine $g) => $g->fill(square(4, 3, 8), ink()));
    $rect = paintOf($raster, fn (RenderingEngine $g) => $g->fillRect(4, 3, 8, 8, ink()));

    expect(bin2hex($filled->dump()))->toBe(bin2hex($rect->dump()));
})->with('rasterize drivers');

it('draws a curve: its inside is ink, the outside beside it is not', function (RasterizeDriver $raster): void {
    $drawn = paintOf($raster, fn (RenderingEngine $g) => $g->fill((new Path())->moveTo(2, 14)->cubicTo(2, -4, 22, -4, 22, 14)->close(), ink()));

    expect($drawn->getPixel(12, 12))->toBe(0xFF0000FF)
        ->and($drawn->getPixel(0, 8))->toBe(0x00000000)
        ->and($drawn->getPixel(23, 8))->toBe(0x00000000);
})->with('rasterize drivers');

it('draws round, square and butt caps beyond the end point only when asked', function (RasterizeDriver $raster): void {
    $line = (new Path())->moveTo(6, 8)->lineTo(18, 8);
    $caps = [];
    foreach ([StrokeCap::BUTT, StrokeCap::SQUARE, StrokeCap::ROUND] as $cap) {
        $caps[$cap->value] = paintOf($raster, fn (RenderingEngine $g) => $g->stroke($line, ink(), new StrokeStyle(width: 6.0, cap: $cap)));
    }

    // 3 pixels past the end of the line, on its axis: only the square and the round cap reach there
    expect($caps['butt']->getPixel(4, 8))->toBe(0x00000000)
        ->and($caps['square']->getPixel(4, 8))->toBe(0xFF0000FF)
        ->and($caps['round']->getPixel(4, 8))->toBe(0xFF0000FF)
        // the corner of the square cap is ink, the round cap's is not
        ->and($caps['square']->getPixel(3, 5))->toBe(0xFF0000FF)
        ->and($caps['round']->getPixel(3, 5))->toBe(0x00000000);
})->with('rasterize drivers');

it('draws the gaps of a dash as not painted', function (RasterizeDriver $raster): void {
    $line = (new Path())->moveTo(2, 8)->lineTo(22, 8);
    $drawn = paintOf($raster, fn (RenderingEngine $g) => $g->stroke($line, ink(), new StrokeStyle(width: 2.0, dash: [4.0, 4.0])));

    expect($drawn->getPixel(3, 8))->toBe(0xFF0000FF)
        ->and($drawn->getPixel(7, 8))->toBe(0x00000000)
        ->and($drawn->getPixel(10, 8))->toBe(0xFF0000FF);
})->with('rasterize drivers');

it('draws a corner with the join asked for', function (RasterizeDriver $raster): void {
    $corner = (new Path())->moveTo(4, 14)->lineTo(4, 4)->lineTo(14, 4);
    $joins = [];
    foreach ([StrokeJoin::MITER, StrokeJoin::BEVEL] as $join) {
        $joins[$join->value] = paintOf($raster, fn (RenderingEngine $g) => $g->stroke($corner, ink(), new StrokeStyle(width: 4.0, join: $join)));
    }

    // the outer corner pixel (2, 2): inside the miter, cut off by the bevel
    expect($joins['miter']->getPixel(2, 2))->toBe(0xFF0000FF)
        ->and($joins['bevel']->getPixel(2, 2))->toBe(0x00000000);
})->with('rasterize drivers');

it('draws a dot for a drawn subpath with no length, under a round cap, and nothing for a bare moveTo', function (): void {
    $dot = (new Path())->moveTo(5, 5)->lineTo(5, 5);
    $closedDot = (new Path())->moveTo(5, 5)->close();
    $bare = (new Path())->moveTo(5, 5);
    $round = new StrokeStyle(width: 4.0, cap: StrokeCap::ROUND);
    $butt = new StrokeStyle(width: 4.0);

    $a = (new RecordingEngine())->commandsOf(fn (RenderingEngine $g) => $g->stroke($dot, ink(), $round));
    $b = (new RecordingEngine())->commandsOf(fn (RenderingEngine $g) => $g->stroke($closedDot, ink(), $round));

    expect($a)->toHaveCount(1)
        ->and($b)->toHaveCount(1)
        ->and((new RecordingEngine())->commandsOf(fn (RenderingEngine $g) => $g->stroke($bare, ink(), $round)))->toBe([])
        ->and((new RecordingEngine())->commandsOf(fn (RenderingEngine $g) => $g->stroke($dot, ink(), $butt)))->toBe([]);
});

it('draws a dotted line from a dash of no length under round caps', function (RasterizeDriver $raster): void {
    $line = (new Path())->moveTo(3, 8)->lineTo(21, 8);
    $drawn = paintOf($raster, fn (RenderingEngine $g) => $g->stroke($line, ink(), new StrokeStyle(width: 4.0, cap: StrokeCap::ROUND, dash: [0.0, 6.0])));

    // dots at 3, 9, 15 and 21; nothing between them
    expect($drawn->getPixel(3, 8))->toBe(0xFF0000FF)
        ->and($drawn->getPixel(9, 8))->toBe(0xFF0000FF)
        ->and($drawn->getPixel(6, 8))->toBe(0x00000000)
        ->and($drawn->getPixel(12, 8))->toBe(0x00000000);
})->with('rasterize drivers');
