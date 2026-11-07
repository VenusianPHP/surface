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

it('names the truetype text scene as exact', function () {
    expect(GpuParity::scenes())->toHaveKey('truetype text')
        ->and(GpuParity::scenes()['truetype text'][0])->toBeTrue();
});

it('names the shadow scene as tolerant by 1', function () {
    expect(GpuParity::scenes()['shadows'][0])->toBeTrue()
        ->and(GpuParity::scenes()['shadows'][2] ?? 0)->toBe(1)
        ->and(GpuParity::scenes()['rounded rects'][0])->toBeFalse();
});

it('lets each channel differ by the tolerance, and no more', function () {
    $off = fn (string $frame, int $by) => substr_replace($frame, chr(255 - $by)."\xff\xff\xff", (1 * 6 + 4) * 4, 4);

    expect(GpuParity::mismatches($off(edgeFrame(), 1), edgeFrame(), 6, 3, true, 1))->toBe([])
        ->and(GpuParity::mismatches($off(edgeFrame(), 2), edgeFrame(), 6, 3, true, 1))->toBe(['(4, 1): fdffffff, Velvet ffffffff']);
});

it('names scenes of both kinds', function () {
    $exact = array_column(GpuParity::scenes(), 0);

    expect($exact)->toContain(true)->toContain(false);
});

it('stretches glyphs in Velvet, the reference, by a scale whose x and y differ', function () {
    $ink = function (float $sx, float $sy): array {
        $v = GpuParity::velvet(64, 32, Edges::ANTIALIASED);
        $v->frame(function ($g) use ($sx, $sy) {
            $g->clear(Surface\NutsAndBolts\Color::rgb(0, 0, 0));
            $g->push()->translate(2, 2)->scale($sx, $sy)->text('Ab', 0, 0, Surface\NutsAndBolts\Color::rgb(255, 255, 255), new Surface\Fonts\ClassicFont)->pop();
        });
        $px = $v->framebuffer()->toRgba8();
        [$xs, $ys] = [[], []];
        for ($y = 0; $y < 32; $y++) {
            for ($x = 0; $x < 64; $x++) {
                if (ord($px[($y * 64 + $x) * 4]) > 0) {
                    [$xs[], $ys[]] = [$x, $y];
                }
            }
        }

        return [max($xs) - min($xs) + 1, max($ys) - min($ys) + 1];
    };
    [$w, $h] = $ink(1, 1);

    expect($ink(2, 1))->toBe([2 * $w, $h])
        ->and($ink(1, 2))->toBe([$w, 2 * $h]);
});
