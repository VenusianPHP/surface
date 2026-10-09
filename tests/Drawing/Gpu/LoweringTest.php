<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\Filter;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\FillRule;
use Surface\Drawing\Gpu\DrawList;
use Surface\Drawing\Gpu\Lowering;
use Surface\Drawing\Gpu\Op;
use Surface\Fonts\ClassicFont;
use Surface\Framebuffers\HdrImage;
use Surface\Framebuffers\Native\NativeFullFramebuffer;
use Surface\NutsAndBolts\Affine;
use Surface\NutsAndBolts\Color;
use Surface\Rasterize\Stroker;

/*
 * Lowering: the base engine's commands to the operations every GPU device
 * draws. Exact lists and exact vertices; coordinates here are whole or half
 * pixels, which 32-bit floats hold exactly.
 */

/** @return list<float> x, y of $count vertices from $first */
function vertices(DrawList $list, int $first, int $count): array
{
    return array_values(unpack('g*', substr($list->vertices, $first * 8, $count * 8)));
}

/** @return list<float> The six vertices of a quad. */
function quadOf(float $x0, float $y0, float $x1, float $y1): array
{
    return [$x0, $y0, $x1, $y0, $x1, $y1, $x0, $y0, $x1, $y1, $x0, $y1];
}

function loweringClip(): Region
{
    return new Region(0, 0, 64, 32);
}

it('lowers a whole clear to one operation and no vertices', function () {
    $list = Lowering::lower([['clear', 0x102030FF]], 64, 32);

    expect($list->operations)->toBe([[Op::CLEAR, 0x102030FF]])
        ->and($list->vertices)->toBe('')
        ->and([$list->width, $list->height, $list->vertexCount()])->toBe([64, 32, 0]);
});

it('lowers a clear of a region to a scissored solid quad', function () {
    $region = new Region(8, 4, 16, 8);
    $list = Lowering::lower([['clear', 0x102030FF, $region]], 64, 32);

    expect($list->operations)->toEqual([[Op::SCISSOR, $region], [Op::SOLID, 0, 0x102030FF]])
        ->and(vertices($list, 0, 6))->toBe(quadOf(8.0, 4.0, 24.0, 12.0));
});

it('lowers a path to its triangles in the stencil and a cover quad over its box', function () {
    $triangle = [[2.0, 2.0], [10.0, 3.0], [4.5, 9.5]];
    $list = Lowering::lower([['path', [$triangle], FillRule::NON_ZERO, 0xFF00FFFF, loweringClip()]], 64, 32);

    expect($list->operations)->toEqual([
        [Op::SCISSOR, loweringClip()],
        [Op::STENCIL_FILL, 0, 3, FillRule::NON_ZERO],
        [Op::COVER, 3, 0xFF00FFFF],
    ])
        ->and(vertices($list, 0, 3))->toBe([2.0, 2.0, 10.0, 3.0, 4.5, 9.5])
        ->and(vertices($list, 3, 6))->toBe(quadOf(2.0, 2.0, 10.0, 10.0))
        ->and($list->vertexCount())->toBe(9);
});

it('turns a contour of n points into n − 2 triangles from its first point, every contour in one list', function () {
    $pentagon = [[0.0, 0.0], [4.0, 0.0], [6.0, 3.0], [3.0, 6.0], [0.0, 4.0]];
    $triangle = [[10.0, 10.0], [14.0, 10.0], [12.0, 14.0]];
    $list = Lowering::lower([['path', [$pentagon, $triangle], FillRule::EVEN_ODD, 0xFFFFFFFF, loweringClip()]], 64, 32);

    expect($list->operations[1])->toBe([Op::STENCIL_FILL, 0, 12, FillRule::EVEN_ODD])
        ->and(vertices($list, 0, 12))->toBe([
            0.0, 0.0, 4.0, 0.0, 6.0, 3.0,
            0.0, 0.0, 6.0, 3.0, 3.0, 6.0,
            0.0, 0.0, 3.0, 6.0, 0.0, 4.0,
            10.0, 10.0, 14.0, 10.0, 12.0, 14.0,
        ])
        ->and(vertices($list, 12, 6))->toBe(quadOf(0.0, 0.0, 14.0, 14.0));
});

it('sets the scissor only when the clip changes', function () {
    $square = [[[1.0, 1.0], [3.0, 1.0], [3.0, 3.0], [1.0, 3.0]]];
    $inner = new Region(4, 4, 8, 8);
    $list = Lowering::lower([
        ['clear', 0x000000FF],
        ['path', $square, FillRule::NON_ZERO, 0xFFFFFFFF, loweringClip()],
        ['path', $square, FillRule::NON_ZERO, 0xFF0000FF, loweringClip()],
        ['path', $square, FillRule::NON_ZERO, 0x00FF00FF, $inner],
        ['path', $square, FillRule::NON_ZERO, 0x0000FFFF, loweringClip()],
    ], 64, 32);

    expect(array_column($list->operations, 0))->toBe([
        Op::CLEAR,
        Op::SCISSOR, Op::STENCIL_FILL, Op::COVER,
        Op::STENCIL_FILL, Op::COVER,
        Op::SCISSOR, Op::STENCIL_FILL, Op::COVER,
        Op::SCISSOR, Op::STENCIL_FILL, Op::COVER,
    ]);
});

it('lowers a polyline as the path of its stroke outline, wound non-zero', function () {
    $points = [[3.0, 3.0], [28.0, 5.0], [16.0, 20.0]];
    $outline = array_map(fn (array $flat): array => array_chunk($flat, 2), Stroker::outline($points, 2.5, true));

    $stroked = Lowering::lower([['polyline', $points, 2.5, true, 0x00C800FF, loweringClip()]], 64, 32);
    $filled = Lowering::lower([['path', $outline, FillRule::NON_ZERO, 0x00C800FF, loweringClip()]], 64, 32);

    expect($stroked->operations)->toEqual($filled->operations)
        ->and($stroked->vertices)->toBe($filled->vertices)
        ->and($stroked->vertexCount())->toBeGreaterThan(6);
});

it('lowers a polyline with nothing to stroke to nothing', function () {
    $list = Lowering::lower([['polyline', [[3.0, 3.0]], 2.0, false, 0xFFFFFFFF, loweringClip()]], 64, 32);

    expect($list->operations)->toBe([])->and($list->vertices)->toBe('');
});

it('lowers an ellipse to one quad a pixel past its radii, carrying the ellipse', function () {
    $list = Lowering::lower([['ellipse', 20.0, 12.0, 7.0, 5.0, 0x28C8FF80, loweringClip()]], 64, 32);

    expect($list->operations)->toEqual([[Op::SCISSOR, loweringClip()], [Op::ELLIPSE, 0, 20.0, 12.0, 7.0, 5.0, 0x28C8FF80]])
        ->and(vertices($list, 0, 6))->toBe(quadOf(12.0, 6.0, 28.0, 18.0));
});

it('lowers a ring to one quad a pixel past its outer edge, carrying the band', function () {
    $list = Lowering::lower([['ring', 16.0, 12.0, 12.0, 9.0, 2.0, 0x3C3CFFFF, loweringClip()]], 64, 32);

    expect($list->operations)->toEqual([[Op::SCISSOR, loweringClip()], [Op::RING, 0, 16.0, 12.0, 12.0, 9.0, 2.0, 0x3C3CFFFF]])
        ->and(vertices($list, 0, 6))->toBe(quadOf(2.0, 1.0, 30.0, 23.0));
});

it('uploads an image source once a frame and draws each placement over its corners', function () {
    $tile = new NativeFullFramebuffer(FormatSpec::rgba8(), 8, 4);
    $other = new NativeFullFramebuffer(FormatSpec::rgba8(), 2, 2);
    $placed = Affine::translation(10.0, 6.0)->multiply(Affine::scaling(2.0, 2.0));
    $list = Lowering::lower([
        ['image', $tile, $placed, 255, Filter::NEAREST, loweringClip()],
        ['image', $other, Affine::translation(1.0, 1.0), 128, Filter::LINEAR, loweringClip()],
        ['image', $tile, Affine::translation(40.0, 2.0), 255, Filter::NEAREST, loweringClip()],
    ], 64, 32);

    expect($list->operations)->toEqual([
        [Op::UPLOAD, 0, $tile],
        [Op::SCISSOR, loweringClip()],
        [Op::IMAGE, 0, 0, $placed->inverse(), 255, Filter::NEAREST],
        [Op::UPLOAD, 1, $other],
        [Op::IMAGE, 6, 1, Affine::translation(1.0, 1.0)->inverse(), 128, Filter::LINEAR],
        [Op::IMAGE, 12, 0, Affine::translation(40.0, 2.0)->inverse(), 255, Filter::NEAREST],
    ])
        ->and($list->operations[0][2])->toBe($tile)
        ->and(vertices($list, 0, 6))->toBe([10.0, 6.0, 26.0, 6.0, 26.0, 14.0, 10.0, 6.0, 26.0, 14.0, 10.0, 14.0]);
});

it('lowers spans to one quad a span', function () {
    $spans = pack('vvvC', 4, 2, 5, 255).pack('vvvC', 5, 3, 1, 255);
    $list = Lowering::lower([['spans', $spans, 0xFFFFFFFF, loweringClip()]], 64, 32);

    expect($list->operations)->toEqual([[Op::SCISSOR, loweringClip()], [Op::RECTS, 0, 12, 0xFFFFFFFF]])
        ->and(vertices($list, 0, 12))->toBe([...quadOf(2.0, 4.0, 7.0, 5.0), ...quadOf(3.0, 5.0, 4.0, 6.0)]);
});

it('lowers a long run of spans', function () {
    $spans = implode('', array_map(fn (int $i): string => pack('vvvC', intdiv($i, 60), $i % 60, 1, 255), range(0, 1799)));
    $list = Lowering::lower([['spans', $spans, 0xFFFFFFFF, loweringClip()]], 64, 32);

    expect($list->operations[1])->toBe([Op::RECTS, 0, 10800, 0xFFFFFFFF])
        ->and($list->vertexCount())->toBe(10800)
        ->and(vertices($list, 10794, 6))->toBe(quadOf(59.0, 29.0, 60.0, 30.0));
});

it('lowers a frame the engine recorded, every operation inside the vertex string', function () {
    $tile = new NativeFullFramebuffer(FormatSpec::rgba8(), 8, 8);
    $commands = (new RecordingEngine(64, 32))->commandsOf(function (RenderingEngine $g) use ($tile): void {
        $g->clear(Color::rgb(0, 0, 0));
        $g->strokeRect(1, 1, 62, 30, Color::rgb(255, 255, 255));
        $g->fillEllipse(10, 16, 4, 3, Color::rgba(255, 128, 0, 0.8));
        $g->strokeEllipse(40, 16, 6, 6, Color::rgb(0, 0, 255), 2);
        $g->text('12', 30, 4, Color::rgb(255, 255, 255), new ClassicFont);
        $g->line(2, 28, 20, 20, Color::rgb(0, 255, 0), 1.5);
        $g->image($tile, 44, 18);
    });

    $list = Lowering::lower($commands, 64, 32);
    $drawn = array_values(array_filter($list->operations, fn (array $op): bool => ! in_array($op[0], [Op::CLEAR, Op::SCISSOR, Op::UPLOAD], true)));

    expect(array_values(array_unique(array_map(fn (array $op): string => $op[0]->name, $list->operations))))
        ->toBe(['CLEAR', 'SCISSOR', 'STENCIL_FILL', 'COVER', 'ELLIPSE', 'RING', 'RECTS', 'UPLOAD', 'IMAGE']);
    foreach ($drawn as $op) {
        $count = in_array($op[0], [Op::STENCIL_FILL, Op::RECTS], true) ? $op[2] : 6;
        expect($op[1] + $count)->toBeLessThanOrEqual($list->vertexCount());
    }
});

it('uploads an HdrImage as itself, so a device can upload its half floats', function () {
    $hdr = HdrImage::fromFloats([2.0, 2.0, 2.0, 1.0], 1, 1);
    $list = Lowering::lower([['image', $hdr, Affine::translation(1.0, 1.0), 255, Filter::LINEAR, loweringClip()]], 64, 32);

    expect($list->operations[0])->toBe([Op::UPLOAD, 0, $hdr]);
});
