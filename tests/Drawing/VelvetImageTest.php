<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\Filter;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\RasterizeDriver;
use Surface\Drawing\Velvet\VelvetGE;
use Surface\Framebuffers\Native\NativeFramebufferDriver;
use Surface\NutsAndBolts\Color;
use Venusian\Surface\Tests\Support\Framebuffers\Rgba8Source;

/*
 * image() with radii, a tint and a source rect, drawn by VelvetGE on the native framebuffer (ext-fb does not paint
 * a tint, a window or a mask yet). The rounded edge is roundRect()'s coverage; the rest is the rule of paintImage().
 */

function solid(int $width, int $height, string $rgba): Rgba8Source
{
    return new Rgba8Source(hex2bin(str_repeat($rgba, $width * $height)), $width, $height);
}

function velvetNative(RasterizeDriver $raster, int $width, int $height, Edges $edges = Edges::ANTIALIASED): array
{
    $buffer = (new NativeFramebufferDriver())->full(FormatSpec::rgba8(), $width, $height);

    return [$buffer, new VelvetGE($buffer, $raster, $edges)];
}

it('cuts an image to a rounded rect: the corner is clear, the middle is the image', function (RasterizeDriver $raster): void {
    [$buffer, $velvet] = velvetNative($raster, 16, 16, Edges::HARD);
    $velvet->frame(fn (RenderingEngine $g) => $g->image(solid(16, 16, 'ff0000ff'), 0, 0, radii: [8, 8, 8, 8]));

    expect($buffer->getPixel(0, 0))->toBe(0x00000000)
        ->and($buffer->getPixel(15, 0))->toBe(0x00000000)
        ->and($buffer->getPixel(0, 15))->toBe(0x00000000)
        ->and($buffer->getPixel(15, 15))->toBe(0x00000000)
        ->and($buffer->getPixel(8, 8))->toBe(0xFF0000FF)
        ->and($buffer->getPixel(8, 0))->toBe(0xFF0000FF);
})->with('rasterize drivers');

it('covers the rounded edge as roundRect() fills it', function (RasterizeDriver $raster): void {
    [$image, $velvet] = velvetNative($raster, 16, 16);
    $velvet->frame(fn (RenderingEngine $g) => $g->image(solid(16, 16, 'ffffffff'), 0, 0, radii: [6, 3, 0, 8]));
    [$fill, $velvetFill] = velvetNative($raster, 16, 16);
    $velvetFill->frame(fn (RenderingEngine $g) => $g->roundRect(0, 0, 16, 16, [6, 3, 0, 8], Color::rgb(255, 255, 255)));

    // an opaque white image through the coverage is the filled rounded rect
    expect(bin2hex($image->dump()))->toBe(bin2hex($fill->dump()));
})->with('rasterize drivers');

it('holds each radius to half the shorter side', function (RasterizeDriver $raster): void {
    [$big, $velvetBig] = velvetNative($raster, 12, 6);
    $velvetBig->frame(fn (RenderingEngine $g) => $g->image(solid(12, 6, 'ffffffff'), 0, 0, radii: [100, 100, 100, 100]));
    [$held, $velvetHeld] = velvetNative($raster, 12, 6);
    $velvetHeld->frame(fn (RenderingEngine $g) => $g->image(solid(12, 6, 'ffffffff'), 0, 0, radii: [3, 3, 3, 3]));

    expect(bin2hex($big->dump()))->toBe(bin2hex($held->dump()));
})->with('rasterize drivers');

it('multiplies the tint and the opacity into the image', function (RasterizeDriver $raster): void {
    [$buffer, $velvet] = velvetNative($raster, 2, 2);
    $velvet->frame(fn (RenderingEngine $g) => $g->image(solid(2, 2, 'ffffffff'), 0, 0, opacity: 0.5, tint: Color::rgba(255, 0, 0, 0.5)));

    // white × (255, 0, 0, 128) = (255, 0, 0, 128); the opacity 128 makes the alpha 64; red 255 over nothing at 64
    expect(bin2hex($buffer->dump()))->toBe(str_repeat('40000040', 4));
})->with('rasterize drivers');

it('draws a source rect scaled onto the destination, and its mirror', function (RasterizeDriver $raster): void {
    $source = new Rgba8Source(hex2bin('ff0000ff'.'00ff00ff'.'0000ffff'.'ffffffff'), 4, 1);
    [$plain, $velvet] = velvetNative($raster, 4, 1);
    $velvet->frame(fn (RenderingEngine $g) => $g->image($source, 0, 0, 4, 1, sourceRect: [1, 0, 2, 1]));
    [$mirror, $velvetMirror] = velvetNative($raster, 4, 1);
    $velvetMirror->frame(fn (RenderingEngine $g) => $g->image($source, 0, 0, 4, 1, sourceRect: [3, 0, -2, 1]));

    // the plain one shows pixels 1 and 2 (GREEN, BLUE), each two wide; the mirror shows the same rect reversed (BLUE, GREEN)
    expect(bin2hex($plain->dump()))->toBe('00ff00ff'.'00ff00ff'.'0000ffff'.'0000ffff')
        ->and(bin2hex($mirror->dump()))->toBe('0000ffff'.'0000ffff'.'00ff00ff'.'00ff00ff');
})->with('rasterize drivers');

it('draws a source rect with a clip and a transform like the whole image', function (RasterizeDriver $raster): void {
    $source = new Rgba8Source(hex2bin('ff0000ff'.'00ff00ff'.'0000ffff'.'ffffffff'), 2, 2);
    [$cropped, $velvet] = velvetNative($raster, 8, 8);
    $velvet->frame(fn (RenderingEngine $g) => $g->push()->translate(2, 2)->scale(2)->image($source, 0, 0, sourceRect: [0, 0, 1, 1], filter: Filter::NEAREST)->pop());
    [$direct, $velvetDirect] = velvetNative($raster, 8, 8);
    $velvetDirect->frame(fn (RenderingEngine $g) => $g->push()->translate(2, 2)->scale(2)->fillRect(0, 0, 1, 1, Color::rgb(255, 0, 0))->pop());

    expect(bin2hex($cropped->dump()))->toBe(bin2hex($direct->dump()));
})->with('rasterize drivers');
