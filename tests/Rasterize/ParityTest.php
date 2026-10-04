<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\FillRule;
use Surface\Rasterize\Extended\ExtendedRasterizeDriver;
use Surface\Rasterize\Extended\ExtendedScanner;
use Surface\Rasterize\Native\NativeRasterizeDriver;
use Surface\Rasterize\Native\NativeScanner;
use Venusian\Surface\Tests\Support\Rasterize\SpanGrid;

/*
 * The same seeded geometry through the PHP scanner and the C scanner: every
 * byte must match. Coordinates mix free floats with integers and half
 * integers, so ties on pixel edges and centres are exercised as often as the
 * open ground between them.
 */

function parityCoordinate(Random\Randomizer $random, float $low, float $high): float
{
    $value = $low + $random->getFloat(0, 1) * ($high - $low);

    return match ($random->getInt(0, 3)) {
        0 => floor($value),
        1 => floor($value) + 0.5,
        default => $value,
    };
}

/** @return array{Region, Random\Randomizer} */
function parityStart(int $seed): array
{
    $random = new Random\Randomizer(new Random\Engine\Mt19937($seed));

    return [new Region($random->getInt(0, 40), $random->getInt(0, 40), $random->getInt(1, 48), $random->getInt(1, 40)), $random];
}

it('scans paths to the same bytes in PHP and in C', function (Edges $edges, int $seed): void {
    [$clip, $random] = parityStart($seed);
    $native = new NativeScanner($clip, $edges);
    $extended = new ExtendedScanner($clip, $edges);

    for ($n = 0; $n < 25; $n++) {
        $contours = [];
        for ($c = 0, $count = $random->getInt(1, 3); $c < $count; $c++) {
            $contour = [];
            for ($p = 0, $points = $random->getInt(3, 12); $p < $points; $p++) {
                $contour[] = parityCoordinate($random, $clip->x - 10, $clip->right() + 10);
                $contour[] = parityCoordinate($random, $clip->y - 10, $clip->bottom() + 10);
            }
            $contours[] = $contour;
        }
        $rule = $random->getInt(0, 1) === 0 ? FillRule::NON_ZERO : FillRule::EVEN_ODD;

        $bytes = $native->path($contours, $rule);
        SpanGrid::assertWellFormed($bytes, $clip, $edges);
        expect(bin2hex($extended->path($contours, $rule)))->toBe(bin2hex($bytes), "path {$n}");
    }
})->with([Edges::HARD, Edges::ANTIALIASED])->with(range(1, 16))->skip(! class_exists(RasterScanner::class), 'ext-rasterize is not loaded in this PHP.');

it('scans ellipses and rings to the same bytes in PHP and in C', function (Edges $edges, int $seed): void {
    [$clip, $random] = parityStart($seed);
    $native = new NativeScanner($clip, $edges);
    $extended = new ExtendedScanner($clip, $edges);

    for ($n = 0; $n < 25; $n++) {
        $cx = parityCoordinate($random, $clip->x - 5, $clip->right() + 5);
        $cy = parityCoordinate($random, $clip->y - 5, $clip->bottom() + 5);
        $rx = parityCoordinate($random, 0, 30);
        $ry = parityCoordinate($random, 0, 30);
        $stroke = parityCoordinate($random, 0, 8);

        expect(bin2hex($extended->ellipse($cx, $cy, $rx, $ry)))->toBe(bin2hex($native->ellipse($cx, $cy, $rx, $ry)), "ellipse {$n}")
            ->and(bin2hex($extended->ring($cx, $cy, $rx, $ry, $stroke)))->toBe(bin2hex($native->ring($cx, $cy, $rx, $ry, $stroke)), "ring {$n}");
    }
})->with([Edges::HARD, Edges::ANTIALIASED])->with(range(1, 16))->skip(! class_exists(RasterScanner::class), 'ext-rasterize is not loaded in this PHP.');

it('scans one-pixel polylines to the same bytes in PHP and in C', function (int $seed): void {
    [$clip, $random] = parityStart($seed);
    $native = new NativeScanner($clip, Edges::HARD);
    $extended = new ExtendedScanner($clip, Edges::HARD);

    for ($n = 0; $n < 25; $n++) {
        $points = [];
        for ($p = 0, $count = $random->getInt(1, 10); $p < $count; $p++) {
            $far = $random->getInt(0, 7) === 0 ? 1e6 : 10;
            $points[] = parityCoordinate($random, $clip->x - $far, $clip->right() + $far);
            $points[] = parityCoordinate($random, $clip->y - $far, $clip->bottom() + $far);
        }
        $closed = $random->getInt(0, 1) === 1;

        expect(bin2hex($extended->polyline($points, $closed)))->toBe(bin2hex($native->polyline($points, $closed)), "polyline {$n}");
    }
})->with(range(1, 16))->skip(! class_exists(RasterScanner::class), 'ext-rasterize is not loaded in this PHP.');

it('rasterizes strokes to the same bytes through either driver', function (Edges $edges, int $seed): void {
    [$clip, $random] = parityStart($seed);
    $native = (new NativeRasterizeDriver())->rasterizer($clip, $edges);
    $extended = (new ExtendedRasterizeDriver())->rasterizer($clip, $edges);

    for ($n = 0; $n < 10; $n++) {
        $points = [];
        for ($p = 0, $count = $random->getInt(2, 8); $p < $count; $p++) {
            $points[] = [parityCoordinate($random, $clip->x - 5, $clip->right() + 5), parityCoordinate($random, $clip->y - 5, $clip->bottom() + 5)];
        }
        $stroke = parityCoordinate($random, 0, 9);
        [$x, $y, $w, $h] = [parityCoordinate($random, $clip->x - 5, $clip->right()), parityCoordinate($random, $clip->y - 5, $clip->bottom()), parityCoordinate($random, 0, 30), parityCoordinate($random, 0, 30)];

        expect(bin2hex($extended->polyline($points, $stroke, $n % 2 === 0)))->toBe(bin2hex($native->polyline($points, $stroke, $n % 2 === 0)), "polyline {$n}")
            ->and(bin2hex($extended->strokeRect($x, $y, $w, $h, $stroke)))->toBe(bin2hex($native->strokeRect($x, $y, $w, $h, $stroke)), "rect {$n}");
    }
})->with([Edges::HARD, Edges::ANTIALIASED])->with(range(1, 8))->skip(! class_exists(RasterScanner::class), 'ext-rasterize is not loaded in this PHP.');
