<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\Edges;
use Surface\Rasterize\Native\NativeRasterizer;

/* Shadows: coverage 1 − smoothstep(−blur, blur, d) at each pixel centre, d the distance to the rounded box. */

function smooth(float $e0, float $e1, float $x): float
{
    $t = min(1.0, max(0.0, ($x - $e0) / ($e1 - $e0)));

    return $t * $t * (3 - 2 * $t);
}

it('blurs a shadow by the smoothstep profile', function () {
    $c = coverageOf((new NativeRasterizer(new Region(0, 0, 64, 64), Edges::ANTIALIASED))->shadow(12, 12, 40, 40, 0, 8));

    for ($k = -8; $k <= 8; $k++) {
        $expected = (int) round((1 - smooth(-8, 8, $k + 0.5)) * 255);
        expect($c[(52 + $k).',32'] ?? 0)->toBe($expected, "k = {$k}")
            ->and($c[(11 - $k).',32'] ?? 0)->toBe($expected, "left, k = {$k}");
    }
});

it('draws a zero-blur shadow as the hard box', function (Edges $edges) {
    $r = new NativeRasterizer(new Region(0, 0, 32, 32), $edges);

    expect($r->shadow(4, 4, 10, 8, 0, 0))->toBe((new NativeRasterizer(new Region(0, 0, 32, 32), Edges::HARD))->fillRect(4, 4, 10, 8))
        ->and($r->shadow(4, 4, 10, 8, 0, -2))->toBe($r->shadow(4, 4, 10, 8, 0, 0));
})->with([Edges::HARD, Edges::ANTIALIASED]);

it('rounds a shadow by its radius and holds the radius', function () {
    $r = new NativeRasterizer(new Region(0, 0, 32, 32), Edges::HARD);

    expect($r->shadow(2, 2, 20, 10, 50, 0))->toBe($r->shadow(2, 2, 20, 10, 5, 0))
        ->and(coverageOf($r->shadow(2, 2, 20, 10, 5, 0)))->not->toHaveKey('2,2');
});

it('thresholds a shadow at 128 with hard edges', function () {
    $soft = coverageOf((new NativeRasterizer(new Region(0, 0, 64, 64), Edges::ANTIALIASED))->shadow(12, 12, 40, 40, 4, 8));
    $hard = coverageOf((new NativeRasterizer(new Region(0, 0, 64, 64), Edges::HARD))->shadow(12, 12, 40, 40, 4, 8));

    expect($hard)->toBe(array_map(fn () => 255, array_filter($soft, fn (int $c) => $c >= 128)));
});

it('cuts shadows to the clip', function (Edges $edges) {
    $r = new NativeRasterizer(new Region(8, 8, 16, 16), $edges);
    $c = coverageOf($r->shadow(2, 2, 12, 12, 3, 4));

    foreach (array_keys($c) as $at) {
        [$x, $y] = array_map(intval(...), explode(',', $at));
        expect($x >= 8 && $x < 24 && $y >= 8 && $y < 24)->toBeTrue($at);
    }
    expect($c)->not->toBe([])
        ->and($r->shadow(40, 40, 5, 5, 1, 2))->toBe('');
})->with([Edges::HARD, Edges::ANTIALIASED]);
