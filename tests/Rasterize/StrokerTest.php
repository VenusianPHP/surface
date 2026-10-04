<?php

declare(strict_types=1);

use Surface\Rasterize\Stroker;

/** Twice the signed area: positive for the orientation every outline contour is turned to. */
function windingArea(array $flat): float
{
    $sum = 0.0;
    $n = intdiv(count($flat), 2);
    for ($i = 0; $i < $n; $i++) {
        $j = ($i + 1) % $n;
        $sum += $flat[2 * $i] * $flat[2 * $j + 1] - $flat[2 * $j] * $flat[2 * $i + 1];
    }

    return $sum;
}

it('outlines a segment as one quad straddling it', function (): void {
    expect(Stroker::outline([[0.0, 2.0], [6.0, 2.0]], 2.0, false))->toBe([[0.0, 1.0, 6.0, 1.0, 6.0, 3.0, 0.0, 3.0]]);
});

it('adds no join where a polyline runs straight on', function (): void {
    expect(Stroker::outline([[0.0, 0.0], [4.0, 0.0], [8.0, 0.0]], 2.0, false))->toHaveCount(2);
});

it('joins a right angle with a miter', function (): void {
    $contours = Stroker::outline([[1.0, 1.0], [5.0, 1.0], [5.0, 5.0]], 2.0, false);

    expect($contours)->toHaveCount(3)
        ->and($contours[2])->toHaveCount(8)
        ->and(array_chunk($contours[2], 2))->toContain([6.0, 0.0]);
});

it('still miters a 40° corner, whose tip sits under three half-strokes out', function (): void {
    // Interior angle 40°: the tip is 1 / sin(20°) ≈ 2.92 half-strokes from the corner.
    $contours = Stroker::outline([[0.0, 0.0], [10.0, 0.0], [10.0 + 10 * cos(deg2rad(140)), 10 * sin(deg2rad(140))]], 2.0, false);

    expect($contours)->toHaveCount(3)
        ->and($contours[2])->toHaveCount(8);
});

it('bevels a corner whose miter would reach past four half-strokes', function (): void {
    $contours = Stroker::outline([[0.0, 0.0], [20.0, 1.0], [0.0, 2.0]], 2.0, false);

    expect($contours)->toHaveCount(3)
        ->and($contours[2])->toHaveCount(6);
});

it('joins every corner of a closed polyline, the closing one included', function (): void {
    expect(Stroker::outline([[0.0, 0.0], [10.0, 0.0], [10.0, 10.0]], 2.0, true))->toHaveCount(6);
});

it('turns every contour the same way, so overlapping pieces unite under non-zero', function (): void {
    foreach ([false, true] as $closed) {
        foreach (Stroker::outline([[0.0, 0.0], [10.0, 3.0], [2.0, 9.0], [12.0, 12.0], [1.0, 1.0]], 3.0, $closed) as $contour) {
            expect(windingArea($contour))->toBeGreaterThan(0.0);
        }
    }
});

it('drops repeated points and draws nothing from fewer than two distinct ones', function (): void {
    expect(Stroker::outline([[1.0, 1.0], [1.0, 1.0], [4.0, 1.0], [4.0, 1.0]], 2.0, false))->toHaveCount(1)
        ->and(Stroker::outline([[1.0, 1.0], [1.0, 1.0]], 2.0, false))->toBe([])
        ->and(Stroker::outline([[1.0, 1.0]], 2.0, true))->toBe([])
        ->and(Stroker::outline([], 2.0, false))->toBe([]);
});

it('outlines a closed pair of points as the one segment between them', function (): void {
    expect(Stroker::outline([[0.0, 2.0], [6.0, 2.0]], 2.0, true))->toBe(Stroker::outline([[0.0, 2.0], [6.0, 2.0]], 2.0, false));
});
