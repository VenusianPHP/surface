<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\FillRule;
use Surface\Framebuffers\Extended\ExtendedFramebufferDriver;
use Surface\Framebuffers\Native\NativeFramebufferDriver;
use Surface\Rasterize\Extended\ExtendedRasterizeDriver;
use Venusian\Surface\Tests\Support\Framebuffers\Formats;

/*
 * Direct fill: the extended rasterizer paints a batch straight into the framebuffer's FbBuffer through ext-fb's span
 * sink. The pixels and the damage are those of the spans path, on every kind of framebuffer that has a sink.
 */

/** @return list<array<int, mixed>> */
function directShapes(): array
{
    $clip = new Region(0, 0, 40, 30);

    return [
        ['path', [[[1.5, 2.0], [30.2, 4.1], [9.0, 25.7]], [[5.0, 5.0], [12.0, 5.0], [12.0, 12.0]]], FillRule::EVEN_ODD, 0x3366CCB0, $clip],
        ['polyline', [[2.0, 27.0], [37.0, 20.0]], 3.5, false, 0x00FF0080, $clip],
        ['ellipse', 20.25, 15.0, 9.5, 6.0, 0xFFFFFF60, $clip],
        ['ring', 20.0, 15.5, 9.0, 7.0, 2.5, 0x0000FFFF, $clip],
        ['roundrect', 2.4, 3.0, 33.1, 26.0, [4.0, 0.0, 6.5, 2.0], 0x80808040, $clip],
        ['roundring', 2.4, 3.0, 33.1, 26.0, [4.0, 0.0, 6.5, 2.0], 1.75, 0x10EE10D0, $clip],
        ['shadow', 6.0, 7.0, 30.0, 22.0, 3.0, 2.5, 0x000000A0, $clip],
        ['ellipse', 500.0, 500.0, 3.0, 3.0, 0xFFFFFFFF, $clip],
    ];
}

it('paints and damages as the spans path does, on every kind with a sink', function (FormatSpec $spec, string $kind, Edges $edges): void {
    $driver = new ExtendedFramebufferDriver;
    $make = fn (): Framebuffer => match ($kind) {
        'full' => $driver->full($spec, 40, 30),
        'dirty' => $driver->dirty($spec, 40, 30),
        'ring' => $driver->ring($spec, 40, 30, 2),
    };
    [$direct, $spanned] = [$make(), $make()];
    $kind === 'dirty' && $direct->beginEpoch() && $spanned->beginEpoch();
    $raster = (new ExtendedRasterizeDriver)->rasterizer(new Region(3, 2, 34, 25), $edges);

    $raster->fillBatch(directShapes(), $direct);
    $raster->fillBatch(directShapes(), $spanned, false);

    expect($direct->spanSink())->toBeInstanceOf(FbBuffer::class)
        ->and(bin2hex($direct->dump()))->toBe(bin2hex($spanned->dump()));
    $kind === 'dirty' && expect($direct->damage())->toEqual($spanned->damage());
})->with([FormatSpec::rgba8(), Formats::rgb565()])->with(['full', 'dirty', 'ring'])->with([Edges::ANTIALIASED, Edges::HARD])
    ->skip(! class_exists(RasterScanner::class) || ! RasterScanner::fills(), 'ext-rasterize with ext-fb\'s span sink is not loaded in this PHP.');

it('has no sink where spans must go through paintSpans()', function (): void {
    expect((new NativeFramebufferDriver)->full(FormatSpec::rgba8(), 4, 4)->spanSink())->toBeNull()
        ->and((new NativeFramebufferDriver)->paged(FormatSpec::rgba8(), 4, 8, 4)->spanSink())->toBeNull();
});

it('falls back to the spans path into a framebuffer with no sink', function (): void {
    [$direct, $spanned] = [(new NativeFramebufferDriver)->full(FormatSpec::rgba8(), 40, 30), (new NativeFramebufferDriver)->full(FormatSpec::rgba8(), 40, 30)];
    $raster = (new ExtendedRasterizeDriver)->rasterizer(new Region(0, 0, 40, 30), Edges::ANTIALIASED);

    $raster->fillBatch(directShapes(), $direct);
    $raster->fillBatch(directShapes(), $spanned, false);

    expect(bin2hex($direct->dump()))->toBe(bin2hex($spanned->dump()));
})->skip(! class_exists(RasterScanner::class), 'ext-rasterize is not loaded in this PHP.');
