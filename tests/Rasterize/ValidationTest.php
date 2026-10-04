<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\RasterizeDriver;
use Surface\Contracts\Rasterize\RasterizeException;
use Surface\Contracts\Rasterize\Rasterizer;

it('refuses a clip that is empty or reaches past 65535', function (RasterizeDriver $driver, array $clip): void {
    expect(fn () => $driver->rasterizer(new Region(...$clip), Edges::HARD))->toThrow(RasterizeException::class, 'is empty or not inside 0..65535');
})->with('rasterize drivers')->with([[[-1, 0, 4, 4]], [[0, -1, 4, 4]], [[0, 0, 0, 4]], [[0, 0, 4, 0]], [[65535, 0, 1, 1]], [[0, 65000, 1, 536]]]);

it('takes a clip that ends exactly at 65535', function (RasterizeDriver $driver): void {
    expect($driver->rasterizer(new Region(65534, 65534, 1, 1), Edges::HARD)->fillRect(65534, 65534, 1, 1))->toBe(pack('vvvC', 65534, 65534, 1, 255));
})->with('rasterize drivers');

it('refuses numbers that are not finite', function (RasterizeDriver $driver, Closure $shape): void {
    expect(fn () => $shape($driver->rasterizer(new Region(0, 0, 8, 8), Edges::ANTIALIASED)))->toThrow(RasterizeException::class, 'is not a finite number');
})->with('rasterize drivers')->with([
    'a rect' => [fn (Rasterizer $r) => $r->fillRect(NAN, 0, 1, 1)],
    'a stroke' => [fn (Rasterizer $r) => $r->line(0, 0, 4, 4, INF)],
    'a radius' => [fn (Rasterizer $r) => $r->fillEllipse(4, 4, -INF, 2)],
    'a point' => [fn (Rasterizer $r) => $r->fillPolygon([[0, 0], [NAN, 4], [4, 4]])],
]);

it('refuses numbers past its limit and takes the limit itself', function (RasterizeDriver $driver): void {
    $r = $driver->rasterizer(new Region(0, 0, 8, 8), Edges::HARD);

    expect(fn () => $r->fillRect(0, 0, Rasterizer::LIMIT * 2, 4))->toThrow(RasterizeException::class, 'is past ±16777216')
        ->and(fn () => $r->line(-Rasterizer::LIMIT - 1, 0, 4, 4))->toThrow(RasterizeException::class)
        ->and(fn () => $r->polyline([[0, 0], [4, Rasterizer::LIMIT + 1]]))->toThrow(RasterizeException::class)
        ->and($r->fillRect(-Rasterizer::LIMIT, -Rasterizer::LIMIT, Rasterizer::LIMIT, Rasterizer::LIMIT))->toBe('')
        ->and($r->line(-Rasterizer::LIMIT, -Rasterizer::LIMIT, Rasterizer::LIMIT, Rasterizer::LIMIT))->not->toBe('');
})->with('rasterize drivers');

it('refuses a point that is not [x, y], naming it', function (RasterizeDriver $driver, array $points): void {
    expect(fn () => $driver->rasterizer(new Region(0, 0, 8, 8), Edges::HARD)->fillPolygon($points))->toThrow(RasterizeException::class, 'Point 1 is not [x, y].');
})->with('rasterize drivers')->with([
    'too short' => [[[0, 0], [1], [2, 2]]],
    'too long' => [[[0, 0], [1, 1, 1], [2, 2]]],
    'a string' => [[[0, 0], ['1', 1], [2, 2]]],
    'not a list' => [[[0, 0], 'x', [2, 2]]],
    'keyed' => [[[0, 0], ['x' => 1, 'y' => 1], [2, 2]]],
]);

it('refuses a contour that is not a list of points', function (RasterizeDriver $driver): void {
    expect(fn () => $driver->rasterizer(new Region(0, 0, 8, 8), Edges::HARD)->fillPath([[[0, 0], [4, 0], [4, 4]], 'x']))->toThrow(RasterizeException::class, 'Contour 1 is not a list of points.');
})->with('rasterize drivers');
