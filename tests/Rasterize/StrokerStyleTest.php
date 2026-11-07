<?php

declare(strict_types=1);

use Surface\Contracts\Rasterize\StrokeCap;
use Surface\Contracts\Rasterize\StrokeJoin;
use Surface\Rasterize\Stroker;

/** The extent [minX, minY, maxX, maxY] of flat contours. */
function extentOf(array $contours): array
{
    $xs = $ys = [];
    foreach ($contours as $flat) {
        foreach (array_chunk($flat, 2) as [$x, $y]) {
            $xs[] = $x;
            $ys[] = $y;
        }
    }

    return [min($xs), min($ys), max($xs), max($ys)];
}

it('ends an open stroke with a butt, a square or a round cap', function (StrokeCap $cap, float $minX, float $maxX): void {
    $contours = Stroker::outline([[0.0, 0.0], [10.0, 0.0]], 4.0, false, $cap);
    [$x0, $y0, $x1, $y1] = extentOf($contours);

    expect([$x0, $x1])->toEqualWithDelta([$minX, $maxX], 1e-9)
        ->and([$y0, $y1])->toEqualWithDelta([-2.0, 2.0], 1e-9);
})->with([
    'butt' => [StrokeCap::BUTT, 0.0, 10.0],
    'square' => [StrokeCap::SQUARE, -2.0, 12.0],
    'round' => [StrokeCap::ROUND, -2.0, 12.0],
]);

it('rounds a cap on a circle about the end point', function (): void {
    $contours = Stroker::outline([[0.0, 0.0], [10.0, 0.0]], 4.0, false, StrokeCap::ROUND);
    $cap = array_values(array_filter($contours, fn (array $c): bool => count($c) > 8));

    expect($cap)->toHaveCount(2);
    foreach ($cap as $flat) {
        foreach (array_chunk($flat, 2) as [$x, $y]) {
            $near = min(hypot($x, $y), hypot($x - 10.0, $y));
            expect($near)->toBeLessThanOrEqual(2.0 + 1e-9);
        }
    }
});

it('makes no cap on a closed stroke', function (): void {
    $square = [[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0]];

    expect(Stroker::outline($square, 2.0, true, StrokeCap::ROUND, StrokeJoin::BEVEL))->toHaveCount(8);
});

it('turns a right-angle corner with a miter, a bevel or a round join', function (): void {
    $corner = [[0.0, 0.0], [10.0, 0.0], [10.0, 10.0]];
    $miter = Stroker::outline($corner, 2.0, false, StrokeCap::BUTT, StrokeJoin::MITER);
    $bevel = Stroker::outline($corner, 2.0, false, StrokeCap::BUTT, StrokeJoin::BEVEL);
    $round = Stroker::outline($corner, 2.0, false, StrokeCap::BUTT, StrokeJoin::ROUND);

    // the miter's tip is the outer corner (11, -1); the bevel stops short of it; the round join stays one half-stroke from (10, 0)
    expect(extentOf($miter))->toEqualWithDelta([0.0, -1.0, 11.0, 10.0], 1e-9)
        ->and(array_filter(array_merge(...array_map(fn ($c) => array_chunk($c, 2), $bevel)), fn (array $p): bool => abs($p[0] - 11.0) < 1e-9 && abs($p[1] + 1.0) < 1e-9))->toBe([])
        ->and(count($round))->toBe(count($bevel))
        ->and(count($round[2]))->toBeGreaterThan(count($bevel[2]));
    foreach (array_chunk($round[2], 2) as [$x, $y]) {
        expect(hypot($x - 10.0, $y))->toBeLessThanOrEqual(1.0 + 1e-9);
    }
});

it('cuts a miter to a bevel past the miter limit', function (): void {
    // a hairpin: the miter tip would be far away
    $sharp = [[0.0, 0.0], [10.0, 0.0], [0.0, 1.0]];
    $long = extentOf(Stroker::outline($sharp, 2.0, false, StrokeCap::BUTT, StrokeJoin::MITER, 100.0));
    $cut = extentOf(Stroker::outline($sharp, 2.0, false, StrokeCap::BUTT, StrokeJoin::MITER, 2.0));

    expect($long[2])->toBeGreaterThan(15.0)
        ->and($cut[2])->toBeLessThan(12.0);
});

it('cuts a polyline into dashes of the pattern, starting where the offset says', function (array $dash, float $offset, array $expected): void {
    $pieces = Stroker::dashed([[0.0, 0.0], [10.0, 0.0]], false, $dash, $offset);

    expect(array_map(fn (array $p): array => [$p['points'][0][0], $p['points'][array_key_last($p['points'])][0]], $pieces))->toEqualWithDelta($expected, 1e-9);
})->with([
    'on 2, off 3' => [[2.0, 3.0], 0.0, [[0.0, 2.0], [5.0, 7.0]]],
    'an offset into the pattern' => [[2.0, 3.0], 1.0, [[0.0, 1.0], [4.0, 6.0], [9.0, 10.0]]],
    'an offset into an off' => [[2.0, 3.0], 3.0, [[2.0, 4.0], [7.0, 9.0]]],
    'a negative offset' => [[2.0, 3.0], -1.0, [[1.0, 3.0], [6.0, 8.0]]],
    'an odd count repeats' => [[4.0], 0.0, [[0.0, 4.0], [8.0, 10.0]]],
]);

it('keeps a corner inside a dash that runs round it', function (): void {
    $pieces = Stroker::dashed([[0.0, 0.0], [10.0, 0.0], [10.0, 10.0]], false, [15.0, 5.0], 0.0);

    expect($pieces)->toHaveCount(1)
        ->and($pieces[0]['points'])->toEqualWithDelta([[0.0, 0.0], [10.0, 0.0], [10.0, 5.0]], 1e-9);
});

it('walks a closed polyline once round, back to its start', function (): void {
    $pieces = Stroker::dashed([[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0]], true, [10.0, 10.0], 0.0);

    expect($pieces)->toHaveCount(2)
        ->and($pieces[0]['points'][0])->toBe([0.0, 0.0])
        ->and($pieces[1]['points'][0])->toEqualWithDelta([10.0, 10.0], 1e-9);
});

it('leaves a zero-length piece for an on element of no length, running the way the line does', function (): void {
    $pieces = Stroker::dashed([[0.0, 0.0], [20.0, 0.0]], false, [0.0, 10.0], 0.0);

    expect(array_map(fn (array $p): array => $p['points'][0], $pieces))->toEqualWithDelta([[0.0, 0.0], [10.0, 0.0], [20.0, 0.0]], 1e-9)
        ->and($pieces[0]['points'][0])->toBe($pieces[0]['points'][1])
        ->and($pieces[1]['direction'])->toBe([1.0, 0.0]);
});

it('joins a dash that runs across the start of a closed polyline, as at any corner', function (): void {
    $square = [[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0]];
    $pieces = Stroker::dashed($square, true, [10.0, 10.0], 5.0);

    expect($pieces)->toHaveCount(2);
    $merged = array_values(array_filter($pieces, fn (array $p): bool => count($p['points']) === 3))[0];
    expect($merged['points'])->toEqualWithDelta([[0.0, 5.0], [0.0, 0.0], [5.0, 0.0]], 1e-9);
});

it('closes a dash that never stops on a closed polyline', function (): void {
    $square = [[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0]];
    $pieces = Stroker::dashed($square, true, [100.0, 1.0], 0.0);

    expect($pieces)->toHaveCount(1)
        ->and($pieces[0]['closed'])->toBeTrue()
        ->and($pieces[0]['points'])->toBe($square);
});

it('draws a dot where a stroke that was drawn has no length: a circle under a round cap, a square under a square one', function (): void {
    $round = Stroker::outline([[5.0, 5.0], [5.0, 5.0]], 4.0, false, StrokeCap::ROUND);
    $square = Stroker::outline([[5.0, 5.0], [5.0, 5.0]], 4.0, false, StrokeCap::SQUARE);
    $turned = Stroker::outline([[5.0, 5.0], [5.0, 5.0]], 4.0, false, StrokeCap::SQUARE, direction: [0.0, 1.0]);

    expect(extentOf($round))->toEqualWithDelta([3.0, 3.0, 7.0, 7.0], 1e-9)
        ->and(extentOf($square))->toEqualWithDelta([3.0, 3.0, 7.0, 7.0], 1e-9)
        ->and(Stroker::outline([[5.0, 5.0], [5.0, 5.0]], 4.0, false, StrokeCap::BUTT))->toBe([])
        ->and(Stroker::outline([[5.0, 5.0]], 4.0, false, StrokeCap::ROUND))->toBe([])
        ->and(extentOf($turned))->toEqualWithDelta([3.0, 3.0, 7.0, 7.0], 1e-9);
    foreach (array_chunk($round[0], 2) as [$x, $y]) {
        expect(hypot($x - 5.0, $y - 5.0))->toEqualWithDelta(2.0, 1e-9);
    }
});

it('turns a square dot to the direction it was given', function (): void {
    $turned = Stroker::outline([[0.0, 0.0], [0.0, 0.0]], 2.0, false, StrokeCap::SQUARE, direction: [M_SQRT1_2, M_SQRT1_2]);

    // a square a side of 2 turned 45 degrees reaches sqrt(2) either way
    expect(extentOf($turned))->toEqualWithDelta([-M_SQRT2, -M_SQRT2, M_SQRT2, M_SQRT2], 1e-9);
});
