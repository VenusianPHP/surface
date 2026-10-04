<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\RasterizeDriver;
use Venusian\Surface\Tests\Support\Rasterize\SpanGrid;

/*
 * Checks that do not come from the rasterizer's own arithmetic: anti-aliased
 * coverage adds up to the shape's area, and one-pixel lines match a
 * step-by-step reference computed another way.
 */

function seeded(int $seed): Random\Randomizer
{
    return new Random\Randomizer(new Random\Engine\Mt19937($seed));
}

function coordinate(Random\Randomizer $random, float $low, float $high): float
{
    return $low + $random->getFloat(0, 1) * ($high - $low);
}

it('covers a triangle with anti-aliased coverage that adds up to its area', function (RasterizeDriver $driver, int $seed): void {
    $random = seeded($seed);
    $clip = new Region(0, 0, 64, 48);
    $points = [];
    for ($i = 0; $i < 3; $i++) {
        $points[] = [coordinate($random, 2, 62), coordinate($random, 2, 46)];
    }
    [[$x0, $y0], [$x1, $y1], [$x2, $y2]] = $points;
    $area = abs(($x1 - $x0) * ($y2 - $y0) - ($x2 - $x0) * ($y1 - $y0)) / 2;
    $perimeter = hypot($x1 - $x0, $y1 - $y0) + hypot($x2 - $x1, $y2 - $y1) + hypot($x0 - $x2, $y0 - $y2);

    $spans = $driver->rasterizer($clip, Edges::ANTIALIASED)->fillTriangle($x0, $y0, $x1, $y1, $x2, $y2);

    SpanGrid::assertWellFormed($spans, $clip, Edges::ANTIALIASED);
    expect(abs(SpanGrid::area($spans) - $area))->toBeLessThan($perimeter / 16 + 1);
})->with('rasterize drivers')->with(range(1, 12));

it('covers an ellipse with anti-aliased coverage that adds up to its area', function (RasterizeDriver $driver, int $seed): void {
    $random = seeded($seed);
    $clip = new Region(0, 0, 64, 48);
    [$rx, $ry] = [coordinate($random, 1, 20), coordinate($random, 1, 15)];
    [$cx, $cy] = [coordinate($random, $rx + 1, 63 - $rx), coordinate($random, $ry + 1, 47 - $ry)];
    $perimeter = 2 * M_PI * sqrt(($rx ** 2 + $ry ** 2) / 2);

    $fill = $driver->rasterizer($clip, Edges::ANTIALIASED)->fillEllipse($cx, $cy, $rx, $ry);
    $ring = $driver->rasterizer($clip, Edges::ANTIALIASED)->strokeEllipse($cx, $cy, $rx, $ry, 0.5);

    SpanGrid::assertWellFormed($fill, $clip, Edges::ANTIALIASED);
    SpanGrid::assertWellFormed($ring, $clip, Edges::ANTIALIASED);
    expect(abs(SpanGrid::area($fill) - M_PI * $rx * $ry))->toBeLessThan($perimeter / 16 + 1)
        ->and(abs(SpanGrid::area($ring) - M_PI * (($rx + 0.25) * ($ry + 0.25) - ($rx - 0.25) * ($ry - 0.25))))->toBeLessThan($perimeter / 8 + 1);
})->with('rasterize drivers')->with(range(1, 12));

it('fills about the area of a shape with hard edges too', function (RasterizeDriver $driver, int $seed): void {
    $random = seeded($seed);
    $clip = new Region(0, 0, 64, 48);
    [$rx, $ry] = [coordinate($random, 2, 20), coordinate($random, 2, 15)];
    [$cx, $cy] = [coordinate($random, $rx + 1, 63 - $rx), coordinate($random, $ry + 1, 47 - $ry)];

    $spans = $driver->rasterizer($clip, Edges::HARD)->fillEllipse($cx, $cy, $rx, $ry);

    SpanGrid::assertWellFormed($spans, $clip, Edges::HARD);
    expect(abs(count(SpanGrid::pixels($spans)) - M_PI * $rx * $ry))->toBeLessThan(2 * M_PI * max($rx, $ry));
})->with('rasterize drivers')->with(range(1, 8));

/**
 * The pixels of a one-pixel line, computed with floats: along the major axis from
 * the lower end, the minor offset is the exact one rounded half up.
 *
 * @return array<string, true>
 */
function referenceLine(int $x0, int $y0, int $x1, int $y1, Region $clip): array
{
    $steep = abs($y1 - $y0) > abs($x1 - $x0);
    [$a0, $b0, $a1, $b1] = $steep ? [$y0, $x0, $y1, $x1] : [$x0, $y0, $x1, $y1];
    if ($a0 > $a1) {
        [$a0, $b0, $a1, $b1] = [$a1, $b1, $a0, $b0];
    }
    $out = [];
    for ($a = $a0; $a <= $a1; $a++) {
        $i = $a - $a0;
        $offset = $a1 === $a0 ? 0 : (int) floor(abs($b1 - $b0) * $i / ($a1 - $a0) + 0.5);
        $b = $b0 + ($b1 >= $b0 ? $offset : -$offset);
        [$x, $y] = $steep ? [$b, $a] : [$a, $b];
        if ($clip->contains($x, $y)) {
            $out["{$x},{$y}"] = true;
        }
    }

    return $out;
}

it('draws one-pixel lines through the pixels the reference picks, inside the clip only', function (RasterizeDriver $driver, int $seed): void {
    $random = seeded($seed);
    $clip = new Region(3, 2, 40, 30);
    for ($n = 0; $n < 40; $n++) {
        [$x0, $y0, $x1, $y1] = [coordinate($random, -20, 60), coordinate($random, -20, 50), coordinate($random, -20, 60), coordinate($random, -20, 50)];
        $spans = $driver->rasterizer($clip, Edges::HARD)->line($x0, $y0, $x1, $y1);

        SpanGrid::assertWellFormed($spans, $clip, Edges::HARD);
        $expected = referenceLine((int) floor($x0), (int) floor($y0), (int) floor($x1), (int) floor($y1), $clip);
        $got = SpanGrid::pixels($spans);
        ksort($expected);
        ksort($got);
        expect($got)->toBe($expected, "line {$n}");
    }
})->with('rasterize drivers')->with(range(1, 4));

it('draws a far-off line through the same pixels as one starting nearby', function (RasterizeDriver $driver): void {
    $clip = new Region(0, 0, 32, 32);
    $near = $driver->rasterizer($clip, Edges::HARD)->line(0, 0, 3000, 1000);
    $far = $driver->rasterizer($clip, Edges::HARD)->line(-3000, -1000, 3000, 1000);

    $expected = referenceLine(-3000, -1000, 3000, 1000, $clip);
    ksort($expected);
    $got = SpanGrid::pixels($far);
    ksort($got);

    expect($got)->toBe($expected)
        ->and($near)->not->toBe('');
})->with('rasterize drivers');

it('paints every pixel of a polyline once, however its segments cross', function (RasterizeDriver $driver, Edges $edges, int $seed): void {
    $random = seeded($seed);
    $clip = new Region(0, 0, 50, 40);
    $points = [];
    for ($i = 0; $i < 8; $i++) {
        $points[] = [coordinate($random, -5, 55), coordinate($random, -5, 45)];
    }

    foreach ([0.5, 1.0, 1.5, 3.0, 7.0] as $stroke) {
        SpanGrid::assertWellFormed($driver->rasterizer($clip, $edges)->polyline($points, $stroke, $seed % 2 === 0), $clip, $edges);
    }
})->with('rasterize drivers')->with([Edges::HARD, Edges::ANTIALIASED])->with(range(1, 6));
