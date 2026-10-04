<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\RasterizeDriver;
use Surface\Framebuffers\Extended\ExtendedFramebufferDriver;
use Surface\Framebuffers\Native\NativeFramebufferDriver;
use Surface\Rasterize\Extended\ExtendedRasterizeDriver;
use Surface\Rasterize\Native\NativeRasterizeDriver;
use Venusian\Surface\Tests\Support\Framebuffers\Formats;

/*
 * Rasterize and Framebuffers meet only at span bytes: rasterize a shape, paint
 * it into a framebuffer, drain it. Every pairing of drivers holds the same bytes.
 */

/** @return array<string, array{RasterizeDriver, FramebufferDriver}> */
function pairings(): array
{
    $rasterize = ['native' => new NativeRasterizeDriver()];
    $framebuffers = ['native' => new NativeFramebufferDriver()];
    if (class_exists(RasterScanner::class)) {
        $rasterize['extended'] = new ExtendedRasterizeDriver();
    }
    if (class_exists(FbBuffer::class)) {
        $framebuffers['extended'] = new ExtendedFramebufferDriver();
    }
    $out = [];
    foreach ($rasterize as $r => $raster) {
        foreach ($framebuffers as $f => $framebuffer) {
            $out["{$r} geometry, {$f} bytes"] = [$raster, $framebuffer];
        }
    }

    return $out;
}

it('paints a rasterized shape into a framebuffer and drains it', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $buffer = $framebuffers->full(FormatSpec::rgba8(), 32, 24);
    $buffer->fill(0x000000FF);
    $r = $raster->rasterizer(Region::wholeSurface(32, 24), Edges::ANTIALIASED);

    $buffer->paintSpans($r->fillEllipse(16, 12, 10, 8), 0xFF8000FF);
    $buffer->paintSpans($r->polyline([[2, 2], [30, 2], [30, 22]], 2), 0x00FF0080);
    $rgba = $buffer->toRgba8();

    $pixel = fn (int $x, int $y): string => bin2hex(substr($rgba, ($y * 32 + $x) * 4, 4));
    $orange = 0.0;
    for ($i = 0; $i < 32 * 24; $i++) {
        $orange += ord($rgba[$i * 4]) / 255;
    }

    expect($pixel(16, 12))->toBe('ff8000ff')
        ->and($pixel(0, 23))->toBe('000000ff')
        ->and($pixel(15, 2))->toBe('008000ff')
        ->and(abs($orange - M_PI * 10 * 8))->toBeLessThan(6);
})->with(pairings());

it('holds the same bytes in every pairing of drivers', function (): void {
    $bytes = [];
    foreach (pairings() as $name => [$raster, $framebuffers]) {
        $buffer = $framebuffers->full(Formats::rgb565(), 40, 30);
        foreach ([Edges::HARD, Edges::ANTIALIASED] as $edges) {
            $r = $raster->rasterizer(new Region(1, 1, 38, 28), $edges);
            $buffer->paintSpans($r->fillPolygon([[3, 3], [37, 8], [20, 27], [5, 20]]), 0x3366CCB0);
            $buffer->paintSpans($r->strokeEllipse(20, 15, 12, 9, 3), 0xFFFFFFFF);
            $buffer->paintSpans($r->line(0, 29, 39, 0), 0xFF0000FF);
        }
        $bytes[$name] = bin2hex($buffer->dump());
    }

    expect(array_unique($bytes))->toHaveCount(1);
})->skip(! class_exists(RasterScanner::class) && ! class_exists(FbBuffer::class), 'needs at least one extended driver to compare against');
