<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Framebuffers\Spans;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\FillRule;
use Surface\Contracts\Rasterize\RasterizeDriver;
use Surface\Contracts\Rasterize\Rasterizer;
use Venusian\Surface\Tests\Support\Rasterize\SpanGrid;

/*
 * Shapes whose spans were worked out by hand from the rules in the spec: a
 * hard-edged pixel is in when its centre is inside; an anti-aliased pixel
 * carries the share of it the shape covers, sampled on 16 lines per row.
 * Every driver answers these exact bytes.
 */

dataset('hard shapes', [
    'a rect on whole pixels' => [[0, 0, 6, 4], fn (Rasterizer $r) => $r->fillRect(1, 1, 3, 2), [
        '......',
        '.###..',
        '.###..',
        '......',
    ]],
    'a rect on half pixels takes the pixels whose centres it holds' => [[0, 0, 4, 3], fn (Rasterizer $r) => $r->fillRect(0.5, 0.5, 2, 2), [
        '##..',
        '##..',
        '....',
    ]],
    'a centre on the right edge is out' => [[0, 0, 3, 1], fn (Rasterizer $r) => $r->fillRect(0.6, 0, 1, 1), [
        '.#.',
    ]],
    'a triangle: a centre on the bottom edge is out' => [[0, 0, 5, 5], fn (Rasterizer $r) => $r->fillTriangle(0, 0, 4, 0, 0, 4), [
        '###..',
        '##...',
        '#....',
        '.....',
        '.....',
    ]],
    'two squares, non-zero: they unite' => [[0, 0, 6, 6], fn (Rasterizer $r) => $r->fillPath([[[0, 0], [4, 0], [4, 4], [0, 4]], [[2, 2], [6, 2], [6, 6], [2, 6]]]), [
        '####..',
        '####..',
        '######',
        '######',
        '..####',
        '..####',
    ]],
    'two squares, even-odd: the overlap is a hole' => [[0, 0, 6, 6], fn (Rasterizer $r) => $r->fillPath([[[0, 0], [4, 0], [4, 4], [0, 4]], [[2, 2], [6, 2], [6, 6], [2, 6]]], FillRule::EVEN_ODD), [
        '####..',
        '####..',
        '##..##',
        '##..##',
        '..####',
        '..####',
    ]],
    'non-zero: a contour wound the other way cuts a hole' => [[0, 0, 6, 6], fn (Rasterizer $r) => $r->fillPath([[[0, 0], [6, 0], [6, 6], [0, 6]], [[2, 2], [2, 4], [4, 4], [4, 2]]]), [
        '######',
        '######',
        '##..##',
        '##..##',
        '######',
        '######',
    ]],
    'non-zero: a contour wound the same way does not' => [[0, 0, 6, 6], fn (Rasterizer $r) => $r->fillPath([[[0, 0], [6, 0], [6, 6], [0, 6]], [[2, 2], [4, 2], [4, 4], [2, 4]]]), [
        '######',
        '######',
        '######',
        '######',
        '######',
        '######',
    ]],
    'a self-intersecting hourglass' => [[0, 0, 4, 4], fn (Rasterizer $r) => $r->fillPolygon([[0, 0], [4, 0], [0, 4], [4, 4]]), [
        '###.',
        '.#..',
        '.#..',
        '###.',
    ]],
    'a circle of radius 3' => [[0, 0, 8, 8], fn (Rasterizer $r) => $r->fillEllipse(4, 4, 3, 3), [
        '........',
        '..####..',
        '.######.',
        '.######.',
        '.######.',
        '.######.',
        '..####..',
        '........',
    ]],
    'a one-pixel line between the floored endpoints' => [[0, 0, 6, 3], fn (Rasterizer $r) => $r->line(0.9, 0.2, 5.5, 2.99), [
        '##....',
        '..##..',
        '....##',
    ]],
    'a zero-length one-pixel line is one pixel' => [[0, 0, 3, 3], fn (Rasterizer $r) => $r->line(1, 1, 1, 1), [
        '...',
        '.#.',
        '...',
    ]],
    'a closed one-pixel polyline paints each corner once' => [[0, 0, 6, 5], fn (Rasterizer $r) => $r->polyline([[1, 1], [4, 1], [4, 3], [1, 3]], closed: true), [
        '......',
        '.####.',
        '.#..#.',
        '.####.',
        '......',
    ]],
    'a one-pixel stroked rect runs through its floored corners' => [[0, 0, 6, 5], fn (Rasterizer $r) => $r->strokeRect(1, 1, 3, 2), [
        '......',
        '.####.',
        '.#..#.',
        '.####.',
        '......',
    ]],
    'a wide stroked rect is the outer rect minus the inner' => [[0, 0, 7, 6], fn (Rasterizer $r) => $r->strokeRect(1, 1, 4, 3, 2), [
        '######.',
        '######.',
        '##..##.',
        '######.',
        '######.',
        '.......',
    ]],
    'a wide line straddles its axis with butt ends' => [[0, 0, 7, 4], fn (Rasterizer $r) => $r->line(0, 2, 6, 2, 2), [
        '.......',
        '######.',
        '######.',
        '.......',
    ]],
    'a wide polyline meets at a miter' => [[0, 0, 7, 6], fn (Rasterizer $r) => $r->polyline([[1, 1], [5, 1], [5, 5]], 2), [
        '.#####.',
        '.#####.',
        '....##.',
        '....##.',
        '....##.',
        '.......',
    ]],
    'a clip keeps only what is inside it' => [[2, 1, 3, 3], fn (Rasterizer $r) => $r->fillRect(-5, -5, 50, 50), [
        '###',
        '###',
        '###',
    ]],
    'a stroked ellipse is a ring' => [[0, 0, 8, 8], fn (Rasterizer $r) => $r->strokeEllipse(4, 4, 3, 3, 2), [
        '..####..',
        '.######.',
        '###..###',
        '##....##',
        '##....##',
        '###..###',
        '.######.',
        '..####..',
    ]],
]);

dataset('antialiased shapes', [
    'half-covered pixels at both ends of a row' => [[0, 0, 4, 1], fn (Rasterizer $r) => $r->fillRect(0.5, 0, 2, 1), [
        '80 ff 80 00',
    ]],
    'eight of sixteen sample lines' => [[0, 0, 1, 1], fn (Rasterizer $r) => $r->fillRect(0, 0.25, 1, 0.5), [
        '80',
    ]],
    'thirteen and three of sixteen sample lines' => [[0, 0, 1, 2], fn (Rasterizer $r) => $r->fillRect(0, 0.2, 1, 1), [
        'cf',
        '30',
    ]],
    'a diagonal halves the pixels it cuts' => [[0, 0, 2, 2], fn (Rasterizer $r) => $r->fillTriangle(0, 0, 2, 0, 0, 2), [
        'ff 80',
        '80 00',
    ]],
    'a one-wide line on an integer row straddles two rows' => [[0, 0, 4, 2], fn (Rasterizer $r) => $r->line(0, 1, 4, 1), [
        '80 80 80 80',
        '80 80 80 80',
    ]],
    'a one-wide line on a pixel centre fills its row' => [[0, 0, 4, 2], fn (Rasterizer $r) => $r->line(0, 1.5, 4, 1.5), [
        '00 00 00 00',
        'ff ff ff ff',
    ]],
    'even-odd overlap is a hole here too' => [[0, 0, 3, 1], fn (Rasterizer $r) => $r->fillPath([[[0, 0], [2, 0], [2, 1], [0, 1]], [[1, 0], [3, 0], [3, 1], [1, 1]]], FillRule::EVEN_ODD), [
        'ff 00 ff',
    ]],
]);

it('rasterizes hard-edged shapes to the hand-worked pixels', function (RasterizeDriver $driver, array $clip, Closure $shape, array $grid): void {
    $clip = new Region(...$clip);
    $spans = $shape($driver->rasterizer($clip, Edges::HARD));

    SpanGrid::assertWellFormed($spans, $clip, Edges::HARD);
    expect(SpanGrid::of($spans, $clip, Edges::HARD))->toBe($grid);
})->with('rasterize drivers')->with('hard shapes');

it('rasterizes anti-aliased shapes to the hand-worked coverage', function (RasterizeDriver $driver, array $clip, Closure $shape, array $grid): void {
    $clip = new Region(...$clip);
    $spans = $shape($driver->rasterizer($clip, Edges::ANTIALIASED));

    SpanGrid::assertWellFormed($spans, $clip, Edges::ANTIALIASED);
    expect(SpanGrid::of($spans, $clip, Edges::ANTIALIASED))->toBe($grid);
})->with('rasterize drivers')->with('antialiased shapes');

it('answers absolute coordinates, joins neighbours of equal coverage, and keeps each row in order', function (RasterizeDriver $driver): void {
    $spans = $driver->rasterizer(new Region(2, 1, 3, 3), Edges::HARD)->fillRect(-5, -5, 50, 50);
    $antialiased = $driver->rasterizer(new Region(0, 0, 4, 1), Edges::ANTIALIASED)->fillRect(0.5, 0, 2, 1);

    expect(Spans::unpack($spans))->toBe([[1, 2, 3, 255], [2, 2, 3, 255], [3, 2, 3, 255]])
        ->and(Spans::unpack($antialiased))->toBe([[0, 0, 1, 128], [0, 1, 1, 255], [0, 2, 1, 128]]);
})->with('rasterize drivers');

it('draws nothing for a zero or negative size, radius or stroke', function (RasterizeDriver $driver, Edges $edges): void {
    $r = $driver->rasterizer(new Region(0, 0, 8, 8), $edges);

    expect($r->fillRect(1, 1, 0, 3))->toBe('')
        ->and($r->fillRect(1, 1, 3, -2))->toBe('')
        ->and($r->strokeRect(1, 1, 3, 3, 0))->toBe('')
        ->and($r->line(1, 1, 5, 5, -1))->toBe('')
        ->and($r->polyline([[1, 1], [5, 5]], 0))->toBe('')
        ->and($r->fillEllipse(4, 4, 0, 3))->toBe('')
        ->and($r->strokeEllipse(4, 4, 3, -1, 1))->toBe('')
        ->and($r->strokeEllipse(4, 4, 3, 3, 0))->toBe('')
        ->and($r->fillPolygon([[1, 1], [5, 5]]))->toBe('')
        ->and($r->fillPath([]))->toBe('')
        ->and($r->fillRect(100, 100, 5, 5))->toBe('');
})->with('rasterize drivers')->with([Edges::HARD, Edges::ANTIALIASED]);

it('reports what it is', function (RasterizeDriver $driver): void {
    $r = $driver->rasterizer(new Region(1, 2, 3, 4), Edges::ANTIALIASED);

    expect($r->driver())->toBe($driver->driver())
        ->and($r->edges())->toBe(Edges::ANTIALIASED)
        ->and($r->clip())->toEqual(new Region(1, 2, 3, 4));
})->with('rasterize drivers');
