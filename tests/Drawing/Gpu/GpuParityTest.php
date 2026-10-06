<?php

declare(strict_types=1);

use Surface\Contracts\Rasterize\Edges;
use Venusian\Surface\Tests\Support\GpuParity\GpuParity;

/*
 * The parity suite every GPU engine package runs against its real device.
 * Here it runs against Velvet itself, which proves the suite, not an engine;
 * the rule that compares two frames is held to hand-made frames.
 */

GpuParity::register('velvet', fn (int $width, int $height, Edges $edges) => GpuParity::velvet($width, $height, $edges));

/** A 6 x 3 frame: two black columns, then white. */
function edgeFrame(): string
{
    return str_repeat(str_repeat("\x00\x00\x00\xff", 2).str_repeat("\xff\xff\xff\xff", 4), 3);
}

/** $frame with the pixel at ($x, $y) of a 6-wide frame made red. */
function withRed(string $frame, int $x, int $y): string
{
    return substr_replace($frame, "\xff\x00\x00\xff", ($y * 6 + $x) * 4, 4);
}

it('passes two equal frames under either rule', function () {
    expect(GpuParity::mismatches(edgeFrame(), edgeFrame(), 6, 3, true))->toBe([])
        ->and(GpuParity::mismatches(edgeFrame(), edgeFrame(), 6, 3, false))->toBe([]);
});

it('lets a pixel beside an edge differ, where the scene is not exact', function () {
    // (2, 1) is white with a black neighbour at (1, 1).
    expect(GpuParity::mismatches(withRed(edgeFrame(), 2, 1), edgeFrame(), 6, 3, false))->toBe([]);
});

it('holds a pixel more than one pixel from an edge exact', function () {
    // (4, 1): all eight around it are white.
    expect(GpuParity::mismatches(withRed(edgeFrame(), 4, 1), edgeFrame(), 6, 3, false))
        ->toBe(['(4, 1): ff0000ff, Velvet ffffffff']);
});

it('holds every pixel exact where the scene is exact', function () {
    expect(GpuParity::mismatches(withRed(edgeFrame(), 2, 1), edgeFrame(), 6, 3, true))
        ->toBe(['(2, 1): ff0000ff, Velvet ffffffff']);
});

it('counts a pixel on the surface\'s border by the neighbours it has', function () {
    // (5, 0): a corner; its three neighbours are white.
    expect(GpuParity::mismatches(withRed(edgeFrame(), 5, 0), edgeFrame(), 6, 3, false))
        ->toBe(['(5, 0): ff0000ff, Velvet ffffffff']);
});

it('reports frames of different sizes', function () {
    expect(GpuParity::mismatches(substr(edgeFrame(), 4), edgeFrame(), 6, 3, true))->toBe(['The frames differ in size: 68 bytes, Velvet 72.']);
});

it('names scenes of both kinds', function () {
    $exact = array_column(GpuParity::scenes(), 0);

    expect($exact)->toContain(true)->toContain(false);
});
