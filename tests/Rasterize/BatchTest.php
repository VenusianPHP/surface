<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\FillRule;
use Surface\Contracts\Rasterize\RasterizeDriver;
use Surface\Contracts\Rasterize\RasterizeException;
use Surface\Contracts\Rasterize\Rasterizer;
use Surface\Contracts\Rasterize\Scanner;
use Surface\Framebuffers\Native\NativeFullFramebuffer;

/*
 * fillBatch() paints what the shape methods answer, shape by shape in order, and a bad shape paints nothing of its
 * batch; Scanner::batch() answers what the calls of the same names answer.
 */

/** @return list<array<int, mixed>> shape commands as RenderingEngine records them, overlapping, translucent, some empty or outside */
function batchShapes(): array
{
    $clip = new Region(0, 0, 40, 30);

    return [
        ['path', [[[1.5, 2.0], [30.2, 4.1], [9.0, 25.7]], [[5.0, 5.0], [12.0, 5.0], [12.0, 12.0]]], FillRule::EVEN_ODD, 0x3366CCB0, $clip],
        ['polyline', [[1.0, 1.0], [38.0, 4.0], [20.0, 28.0]], 1.0, true, 0xFF0000FF, $clip],
        ['polyline', [[2.0, 27.0], [37.0, 20.0]], 3.5, false, 0x00FF0080, $clip],
        ['ellipse', 20.25, 15.0, 9.5, 6.0, 0xFFFFFF60, $clip],
        ['ring', 20.0, 15.5, 9.0, 7.0, 2.5, 0x0000FFC0, $clip],
        ['roundrect', 2.4, 3.0, 33.1, 26.0, [4.0, 0.0, 6.5, 2.0], 0x80808040, $clip],
        ['roundring', 2.4, 3.0, 33.1, 26.0, [4.0, 0.0, 6.5, 2.0], 1.75, 0x10EE10D0, $clip],
        ['shadow', 6.0, 7.0, 30.0, 22.0, 3.0, 2.5, 0x000000A0, $clip],
        ['ellipse', 500.0, 500.0, 3.0, 3.0, 0xFFFFFFFF, $clip],
        ['roundrect', 4.0, 4.0, 4.0, 9.0, [1.0, 1.0, 1.0, 1.0], 0xFFFFFFFF, $clip],
    ];
}

function shapeSpans(Rasterizer $r, array $c): string
{
    return match ($c[0]) {
        'path' => $r->fillPath($c[1], $c[2]),
        'polyline' => $r->polyline($c[1], $c[2], $c[3]),
        'ellipse' => $r->fillEllipse($c[1], $c[2], $c[3], $c[4]),
        'ring' => $r->strokeEllipse($c[1], $c[2], $c[3], $c[4], $c[5]),
        'roundrect' => $r->fillRoundRect($c[1], $c[2], $c[3] - $c[1], $c[4] - $c[2], $c[5]),
        'roundring' => $r->strokeRoundRect($c[1], $c[2], $c[3] - $c[1], $c[4] - $c[2], $c[5], $c[6]),
        'shadow' => $r->shadow($c[1], $c[2], $c[3] - $c[1], $c[4] - $c[2], $c[5], $c[6]),
    };
}

it('paints a batch as the shape methods paint it, shape by shape', function (RasterizeDriver $driver, Edges $edges): void {
    $raster = $driver->rasterizer(new Region(3, 2, 34, 25), $edges);
    [$batched, $one] = [new NativeFullFramebuffer(FormatSpec::rgba8(), 40, 30), new NativeFullFramebuffer(FormatSpec::rgba8(), 40, 30)];
    $batched->fill(0x204060FF);
    $one->fill(0x204060FF);

    $raster->fillBatch(batchShapes(), $batched);
    foreach (batchShapes() as $c) {
        $spans = shapeSpans($raster, $c);
        $spans === '' || $one->paintSpans($spans, $c[array_key_last($c) - 1]);
    }

    expect(bin2hex($batched->dump()))->toBe(bin2hex($one->dump()));
})->with('rasterize drivers')->with([Edges::ANTIALIASED, Edges::HARD]);

it('paints nothing of a batch with a bad shape in it', function (RasterizeDriver $driver, array $bad, string $why): void {
    $buffer = new NativeFullFramebuffer(FormatSpec::rgba8(), 40, 30);

    expect(fn () => $driver->rasterizer(new Region(0, 0, 40, 30), Edges::ANTIALIASED)->fillBatch([...batchShapes(), $bad], $buffer))->toThrow(RasterizeException::class, $why)
        ->and(bin2hex($buffer->dump()))->toBe(str_repeat('00', 40 * 30 * 4));
})->with('rasterize drivers')->with([
    'an unknown kind' => [['image', 1, 2], 'Shape 10 is not a path, polyline, ellipse, ring, roundrect, roundring or shadow command'],
    'a number past the limit' => [['ellipse', 1e300, 4.0, 2.0, 2.0, 0xFFFFFFFF], 'cx'],
    'bad radii' => [['roundrect', 0.0, 0.0, 4.0, 4.0, [1.0, 1.0], 0xFFFFFFFF], 'Radii are a list of four numbers'],
    'a bad point' => [['path', [[[0.0, 0.0], [4.0]]], FillRule::NON_ZERO, 0xFFFFFFFF], 'Point 1'],
]);

it('answers a scanner batch as the calls of the same names', function (RasterizeDriver $driver, Edges $edges): void {
    $scanner = (fn () => $this->scanner)->call($driver->rasterizer(new Region(0, 0, 40, 30), $edges));
    $radii = [4.0, 0.0, 6.5, 2.0];

    expect(array_map('bin2hex', $scanner->batch([
        [Scanner::PATH, [[1.5, 2.0, 30.2, 4.1, 9.0, 25.7], [5.0, 5.0, 12.0, 5.0, 12.0, 12.0]], 1],
        [Scanner::ELLIPSE, 20.25, 15.0, 9.5, 6.0],
        [Scanner::RING, 20.0, 15.5, 9.0, 7.0, 2.5],
        [Scanner::ROUND_RECT, 2.4, 3.0, 33.1, 26.0, $radii],
        [Scanner::ROUND_RING, 2.4, 3.0, 33.1, 26.0, $radii, 1.75],
        [Scanner::SHADOW, 6.0, 7.0, 30.0, 22.0, 3.0, 2.5],
        [Scanner::POLYLINE, [1.0, 1.0, 38.0, 4.0, 20.0, 28.0], true],
    ])))->toBe(array_map('bin2hex', [
        $scanner->path([[1.5, 2.0, 30.2, 4.1, 9.0, 25.7], [5.0, 5.0, 12.0, 5.0, 12.0, 12.0]], FillRule::EVEN_ODD),
        $scanner->ellipse(20.25, 15.0, 9.5, 6.0),
        $scanner->ring(20.0, 15.5, 9.0, 7.0, 2.5),
        $scanner->roundRect(2.4, 3.0, 33.1, 26.0, $radii),
        $scanner->roundRing(2.4, 3.0, 33.1, 26.0, $radii, 1.75),
        $scanner->shadow(6.0, 7.0, 30.0, 22.0, 3.0, 2.5),
        $scanner->polyline([1.0, 1.0, 38.0, 4.0, 20.0, 28.0], true),
    ]))->and($scanner->batch([]))->toBe([]);
})->with('rasterize drivers')->with([Edges::ANTIALIASED, Edges::HARD]);

it('numbers its ops as ext-rasterize does', function (): void {
    expect([Scanner::PATH, Scanner::ELLIPSE, Scanner::RING, Scanner::ROUND_RECT, Scanner::ROUND_RING, Scanner::SHADOW, Scanner::POLYLINE])
        ->toBe([RASTER_OP_PATH, RASTER_OP_ELLIPSE, RASTER_OP_RING, RASTER_OP_ROUND_RECT, RASTER_OP_ROUND_RING, RASTER_OP_SHADOW, RASTER_OP_POLYLINE]);
})->skip(! defined('RASTER_OP_PATH'), 'ext-rasterize with batch() is not loaded in this PHP.');
