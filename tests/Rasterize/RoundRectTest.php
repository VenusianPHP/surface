<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Framebuffers\Spans;
use Surface\Contracts\Rasterize\Edges;
use Surface\Rasterize\Native\NativeRasterizer;

/* Rounded rects in the native scanner: one interval a sample line, each corner cut by its own radius. */

function roundRaster(Edges $edges, int $w = 32, int $h = 32, int $x = 0, int $y = 0): NativeRasterizer
{
    return new NativeRasterizer(new Region($x, $y, $w, $h), $edges);
}

/** @return array<string, int> "x,y" => coverage */
function coverageOf(string $spans): array
{
    $out = [];
    foreach (Spans::unpack($spans) as [$y, $x, $length, $coverage]) {
        for ($i = 0; $i < $length; $i++) {
            $out[($x + $i).','.$y] = $coverage;
        }
    }

    return $out;
}

it('fills a rounded rect with no radii as fillRect does', function (Edges $edges) {
    $r = roundRaster($edges);

    expect($r->fillRoundRect(2, 2, 10, 6, [0, 0, 0, 0]))->toBe($r->fillRect(2, 2, 10, 6))
        ->and($r->fillRoundRect(2.3, 1.7, 9.4, 6.2, [0, 0, 0, 0]))->toBe($r->fillRect(2.3, 1.7, 9.4, 6.2));
})->with([Edges::HARD, Edges::ANTIALIASED]);

it('cuts each corner by its own radius', function () {
    $c = coverageOf(roundRaster(Edges::HARD)->fillRoundRect(0, 0, 20, 20, [8, 0, 0, 0]));

    expect(isset($c['0,0']))->toBeFalse()
        ->and($c['19,0'] ?? 0)->toBe(255)
        ->and($c['2,2'] ?? 0)->toBe(255)
        ->and($c['0,19'] ?? 0)->toBe(255)
        ->and($c['19,19'] ?? 0)->toBe(255);
});

it('matches the ellipse where a rounded rect is a circle', function (Edges $edges) {
    $r = roundRaster($edges);

    expect($r->fillRoundRect(0, 0, 20, 20, [10, 10, 10, 10]))->toBe($r->fillEllipse(10, 10, 10, 10));
})->with([Edges::HARD, Edges::ANTIALIASED]);

it('holds radii to half the short side and above zero', function (Edges $edges) {
    $r = roundRaster($edges);

    expect($r->fillRoundRect(1, 1, 20, 10, [50, -3, 0, 0]))->toBe($r->fillRoundRect(1, 1, 20, 10, [5, 0, 0, 0]));
})->with([Edges::HARD, Edges::ANTIALIASED]);

it('strokes a band inside the rect', function () {
    $r = roundRaster(Edges::HARD);
    $band = coverageOf($r->strokeRoundRect(2, 2, 24, 16, [6, 3, 0, 5], 2));
    $inner = coverageOf($r->fillRoundRect(4, 4, 20, 12, [4, 1, 0, 3]));
    $outer = coverageOf($r->fillRoundRect(2, 2, 24, 16, [6, 3, 0, 5]));

    expect(array_intersect_key($band, $inner))->toBe([])
        ->and(array_keys($band + $inner))->toEqualCanonicalizing(array_keys($outer));
});

it('fills the whole rect when the border reaches the middle', function (Edges $edges) {
    $r = roundRaster($edges);

    expect($r->strokeRoundRect(2, 2, 20, 10, [3, 3, 3, 3], 5))->toBe($r->fillRoundRect(2, 2, 20, 10, [3, 3, 3, 3]));
})->with([Edges::HARD, Edges::ANTIALIASED]);

it('cuts rounded rects to the clip', function (Edges $edges) {
    $r = roundRaster($edges, 16, 16, 8, 8);
    $straddling = coverageOf($r->fillRoundRect(2, 2, 12, 12, [4, 4, 4, 4]).$r->strokeRoundRect(18, 18, 10, 10, [3, 3, 3, 3], 2));

    foreach (array_keys($straddling) as $at) {
        [$x, $y] = array_map(intval(...), explode(',', $at));
        expect($x >= 8 && $x < 24 && $y >= 8 && $y < 24)->toBeTrue($at);
    }
    expect($straddling)->not->toBe([])
        ->and($r->fillRoundRect(30, 0, 5, 5, [2, 2, 2, 2]))->toBe('')
        ->and($r->strokeRoundRect(0, 30, 5, 5, [2, 2, 2, 2], 1))->toBe('');
})->with([Edges::HARD, Edges::ANTIALIASED]);
