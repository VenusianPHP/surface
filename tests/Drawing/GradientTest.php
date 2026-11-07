<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Drawing\StrokeStyle;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\RasterizeDriver;
use Surface\Drawing\Velvet\VelvetGE;
use Surface\Framebuffers\Native\NativeFramebufferDriver;
use Surface\NutsAndBolts\Affine;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\Gradient;
use Surface\NutsAndBolts\GradientSpread;
use Surface\NutsAndBolts\LinearGradient;
use Surface\NutsAndBolts\Path;
use Surface\NutsAndBolts\RadialGradient;

/*
 * A gradient is baked into a ramp of 256 colours; a point picks the entry floor(t × 255 + ½) of its position t along
 * the gradient, spread applied. Velvet paints it through the path's coverage.
 */

function blackToWhite(float $x0 = 0, float $x1 = 255, GradientSpread $spread = GradientSpread::PAD): LinearGradient
{
    return new LinearGradient($x0, 0, $x1, 0, [[0.0, Color::rgb(0, 0, 0)], [1.0, Color::rgb(255, 255, 255)]], $spread);
}

it('bakes the stops into a ramp of 256 colours, interpolated per channel and rounded to a byte', function (): void {
    $ramp = blackToWhite()->ramp();

    expect(strlen($ramp))->toBe(1024)
        ->and(bin2hex(substr($ramp, 0, 4)))->toBe('000000ff')
        ->and(bin2hex(substr($ramp, 100 * 4, 4)))->toBe('646464ff')
        ->and(bin2hex(substr($ramp, 255 * 4, 4)))->toBe('ffffffff');
});

it('holds the end colours outside the first and last stop, and takes the later stop at equal offsets', function (): void {
    $red = Color::rgb(255, 0, 0);
    $blue = Color::rgb(0, 0, 255);
    $ramp = (new LinearGradient(0, 0, 1, 0, [[0.25, $red], [0.5, $red], [0.5, $blue], [0.75, $blue]]))->ramp();
    $at = fn (int $i): string => bin2hex(substr($ramp, $i * 4, 4));

    expect($at(0))->toBe('ff0000ff')
        ->and($at(127))->toBe('ff0000ff')
        ->and($at(128))->toBe('0000ffff')
        ->and($at(255))->toBe('0000ffff');
});

it('interpolates alpha like the colours', function (): void {
    $ramp = (new LinearGradient(0, 0, 1, 0, [[0.0, Color::rgba(255, 0, 0, 0.0)], [1.0, Color::rgba(255, 0, 0, 1.0)]]))->ramp();

    expect(bin2hex(substr($ramp, 0, 4)))->toBe('ff000000')
        ->and(bin2hex(substr($ramp, 255 * 4, 4)))->toBe('ff0000ff');
});

it('turns a position into a ramp entry, spread past the ends', function (GradientSpread $spread, float $t, int $entry): void {
    expect(Gradient::index(Gradient::at('linear', $spread, $t, 0.0)))->toBe($entry);
})->with([
    'pad before' => [GradientSpread::PAD, -0.5, 0],
    'pad after' => [GradientSpread::PAD, 1.5, 255],
    'pad inside' => [GradientSpread::PAD, 0.5, 128],
    'repeat' => [GradientSpread::REPEAT, 1.25, 64],
    'repeat before' => [GradientSpread::REPEAT, -0.25, 191],
    'reflect' => [GradientSpread::REFLECT, 1.25, 191],
    'reflect again' => [GradientSpread::REFLECT, 2.25, 64],
]);

it('measures a radial gradient from its centre', function (): void {
    expect(Gradient::at('radial', GradientSpread::PAD, 0.3, 0.4))->toEqualWithDelta(0.5, 1e-12);
});

it('maps the surface into a linear gradient so that its start is 0 and its end is 1', function (): void {
    $gradient = blackToWhite(10, 30);
    $inverse = $gradient->toGradientSpace(Affine::translation(100, 0));
    [$u0] = $inverse->apply(110.0, 5.0);
    [$u1] = $inverse->apply(130.0, 5.0);

    expect($u0)->toEqualWithDelta(0.0, 1e-9)
        ->and($u1)->toEqualWithDelta(1.0, 1e-9);
});

it('keeps a gradient the same under a uniform scale of the transform and the path alike', function (): void {
    $radial = new RadialGradient(10, 10, 5, [[0.0, Color::rgb(0, 0, 0)], [1.0, Color::rgb(255, 255, 255)]]);
    $inverse = $radial->toGradientSpace(Affine::scaling(2, 2));
    [$u, $v] = $inverse->apply(20.0 + 10.0, 20.0);

    expect(hypot($u, $v))->toEqualWithDelta(1.0, 1e-9);
});

it('refuses a gradient that is not one', function (Closure $make): void {
    expect($make)->toThrow(InvalidArgumentException::class);
})->with([
    'no stops' => [fn () => new LinearGradient(0, 0, 1, 0, [])],
    'an offset past 1' => [fn () => new LinearGradient(0, 0, 1, 0, [[0.0, Color::rgb(0, 0, 0)], [1.5, Color::rgb(0, 0, 0)]])],
    'falling offsets' => [fn () => new LinearGradient(0, 0, 1, 0, [[0.5, Color::rgb(0, 0, 0)], [0.25, Color::rgb(0, 0, 0)]])],
    'a stop that is not a pair' => [fn () => new LinearGradient(0, 0, 1, 0, [[0.5]])],
    'one point' => [fn () => new LinearGradient(3, 3, 3, 3, [[0.0, Color::rgb(0, 0, 0)]])],
    'a radius of 0' => [fn () => new RadialGradient(0, 0, 0, [[0.0, Color::rgb(0, 0, 0)]])],
    'a number that is not finite' => [fn () => new RadialGradient(NAN, 0, 5, [[0.0, Color::rgb(0, 0, 0)]])],
]);

function squareOf(float $x, float $y, float $side): Path
{
    return (new Path())->moveTo($x, $y)->lineTo($x + $side, $y)->lineTo($x + $side, $y + $side)->lineTo($x, $y + $side)->close();
}

it('records a gradient fill as its own command, with the ramp and the way back to the gradient', function (): void {
    $commands = (new RecordingEngine())->commandsOf(fn (RenderingEngine $g) => $g->translate(4, 0)->fill(squareOf(0, 0, 10), blackToWhite(0, 10)));

    expect($commands[0][0])->toBe('gradpath')
        ->and($commands[0][3]['kind'])->toBe('linear')
        ->and($commands[0][3]['spread'])->toBe(GradientSpread::PAD)
        ->and(strlen($commands[0][3]['ramp']))->toBe(1024)
        ->and($commands[0][3]['inverse']->apply(14.0, 0.0)[0])->toEqualWithDelta(1.0, 1e-9);
});

it('records a gradient stroke as its outline', function (): void {
    $line = (new Path())->moveTo(0, 5)->lineTo(20, 5);
    $commands = (new RecordingEngine())->commandsOf(fn (RenderingEngine $g) => $g->stroke($line, blackToWhite(0, 20), new StrokeStyle(width: 4.0)));

    expect($commands[0][0])->toBe('gradpath')
        ->and($commands[0][1])->toHaveCount(1);
});

function shaded(RasterizeDriver $raster, Closure $draw, int $width = 16, int $height = 4): Surface\Contracts\Framebuffers\Framebuffer
{
    $buffer = (new NativeFramebufferDriver())->full(FormatSpec::rgba8(), $width, $height);
    (new VelvetGE($buffer, $raster, Edges::HARD))->frame($draw);

    return $buffer;
}

it('paints a linear gradient across a path: each pixel the ramp entry of its centre', function (RasterizeDriver $raster): void {
    $drawn = shaded($raster, fn (RenderingEngine $g) => $g->fill(squareOf(0, 0, 4), blackToWhite(0, 4)), 4, 4);

    // t at the centres 0.5, 1.5, 2.5, 3.5 over 4 pixels: 0.125, 0.375, 0.625, 0.875 -> entries 32, 96, 159, 223
    expect(dechex($drawn->getPixel(0, 1)))->toBe('202020ff')
        ->and(dechex($drawn->getPixel(1, 1)))->toBe('606060ff')
        ->and(dechex($drawn->getPixel(2, 1)))->toBe('9f9f9fff')
        ->and(dechex($drawn->getPixel(3, 1)))->toBe('dfdfdfff')
        ->and($drawn->getPixel(0, 0))->toBe($drawn->getPixel(0, 3));
})->with('rasterize drivers');

it('paints a radial gradient out from its centre, and a gradient stroke along its outline', function (RasterizeDriver $raster): void {
    $radial = new RadialGradient(8, 8, 8, [[0.0, Color::rgb(255, 0, 0)], [1.0, Color::rgb(0, 0, 255)]]);
    $disc = shaded($raster, fn (RenderingEngine $g) => $g->fill(squareOf(0, 0, 16), $radial), 16, 16);
    $line = shaded($raster, fn (RenderingEngine $g) => $g->stroke((new Path())->moveTo(0, 2)->lineTo(16, 2), blackToWhite(0, 16), new StrokeStyle(width: 2.0)));

    // red at the centre, bluer toward the corner; the stroke runs dark to light, nothing beside it
    $middle = $disc->getPixel(8, 8);
    $corner = $disc->getPixel(0, 0);
    expect(($middle >> 24) & 0xFF)->toBeGreaterThan(230)
        ->and(($corner >> 8) & 0xFF)->toBeGreaterThan(230)
        ->and(($line->getPixel(1, 2) >> 24) & 0xFF)->toBeLessThan(($line->getPixel(14, 2) >> 24) & 0xFF)
        ->and($line->getPixel(8, 0))->toBe(0x00000000);
})->with('rasterize drivers');

it('lets a see-through gradient show what is under it', function (RasterizeDriver $raster): void {
    $clear = new LinearGradient(0, 0, 4, 0, [[0.0, Color::rgba(255, 0, 0, 0.0)], [1.0, Color::rgba(255, 0, 0, 1.0)]]);
    $drawn = shaded($raster, fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 255))->fill(squareOf(0, 0, 4), $clear), 4, 4);

    expect(($drawn->getPixel(0, 1) >> 8) & 0xFF)->toBeGreaterThan(200)   // still mostly blue
        ->and(($drawn->getPixel(3, 1) >> 24) & 0xFF)->toBeGreaterThan(200); // mostly red
})->with('rasterize drivers');

it('repeats and reflects past the ends', function (RasterizeDriver $raster): void {
    $repeat = shaded($raster, fn (RenderingEngine $g) => $g->fill(squareOf(0, 0, 8), blackToWhite(0, 4, GradientSpread::REPEAT)), 8, 8);
    $reflect = shaded($raster, fn (RenderingEngine $g) => $g->fill(squareOf(0, 0, 8), blackToWhite(0, 4, GradientSpread::REFLECT)), 8, 8);

    expect($repeat->getPixel(4, 1))->toBe($repeat->getPixel(0, 1))
        ->and($reflect->getPixel(4, 1))->toBe($reflect->getPixel(3, 1));
})->with('rasterize drivers');
