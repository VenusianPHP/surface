<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Rasterize\RasterizeDriver;
use Surface\Drawing\Velvet\VelvetGE;
use Surface\NutsAndBolts\Color;

/*
 * Velvet draws roundRect and shadow exactly: a signed distance per pixel,
 * anti-aliased over 1 px, on a 64x64 rgba8 framebuffer cleared to transparent.
 */

function velvet64(RasterizeDriver $raster, FramebufferDriver $framebuffers, callable $draw): Surface\Contracts\Framebuffers\Framebuffer
{
    $buffer = $framebuffers->full(FormatSpec::rgba8(), 64, 64);
    (new VelvetGE($buffer, $raster))->frame(function (RenderingEngine $g) use ($draw): void {
        $g->clear(new Color(0, 0, 0, 0));
        $draw($g);
    });

    return $buffer;
}

it('fills the centre and leaves the corner outside the arc clear', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $buffer = velvet64($raster, $framebuffers, fn ($g) => $g->roundRect(12, 12, 40, 40, [8, 8, 8, 8], Color::rgb(255, 0, 0)));

    expect($buffer->getPixel(32, 32))->toBe(0xFF0000FF)
        ->and($buffer->getPixel(12, 12))->toBe(0x00000000)  // outside the top-left arc
        ->and($buffer->getPixel(51, 51))->toBe(0x00000000)  // outside the bottom-right arc
        ->and($buffer->getPixel(32, 12))->toBe(0xFF0000FF)  // the top edge row is inside
        ->and($buffer->getPixel(32, 11))->toBe(0x00000000);
})->with('engine pairings');

it('draws a 1 px inside border on the edge row and the fill one row in', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $buffer = velvet64($raster, $framebuffers, fn ($g) => $g->roundRect(12, 12, 40, 40, [8, 8, 8, 8], Color::rgb(255, 0, 0), Color::rgb(0, 0, 255), 1));

    expect($buffer->getPixel(32, 12))->toBe(0x0000FFFF)
        ->and($buffer->getPixel(32, 13))->toBe(0xFF0000FF)
        ->and($buffer->getPixel(51, 32))->toBe(0x0000FFFF)
        ->and($buffer->getPixel(32, 32))->toBe(0xFF0000FF);
})->with('engine pairings');

it('draws a shadow that fades out away from the rect and stays within the blur', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $buffer = velvet64($raster, $framebuffers, fn ($g) => $g->shadow(16, 16, 32, 32, 4, 4, Color::rgba(0, 0, 0, 0.5)));
    $alpha = fn (int $x, int $y): int => $buffer->getPixel($x, $y) & 0xFF;

    expect($alpha(32, 32))->toBeGreaterThan($alpha(32, 16 - 8 + 1))
        ->and($alpha(32, 32))->toBeGreaterThan(100)
        ->and($alpha(32, 16 - 8 + 1))->toBe(0)   // 2 blur widths above the rect (shadow sits blur/4 low)
        ->and($alpha(5, 32))->toBe(0);
})->with('engine pairings');

it('draws the same pixels on every call', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $draw = fn ($g) => $g->shadow(10, 10, 30, 20, 6, 3, Color::rgba(0, 0, 0, 0.5))->roundRect(10.3, 10.7, 30, 20, [6, 2, 6, 2], Color::rgba(40, 200, 255, 0.8), Color::rgb(255, 255, 255), 1.5);

    expect(velvet64($raster, $framebuffers, $draw)->dump())->toBe(velvet64($raster, $framebuffers, $draw)->dump());
})->with('engine pairings');

it('falls back to paths under a turning transform', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $buffer = velvet64($raster, $framebuffers, fn ($g) => $g->translate(32, 32)->rotate(M_PI / 4)->roundRect(-10, -10, 20, 20, [4, 4, 4, 4], Color::rgb(255, 0, 0)));

    expect($buffer->getPixel(32, 32))->toBe(0xFF0000FF)
        ->and($buffer->getPixel(0, 0))->toBe(0x00000000);
})->with('engine pairings');

it('covers an arc pixel by its signed distance and the shadow centre by 1 - smoothstep', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $rect = velvet64($raster, $framebuffers, fn ($g) => $g->roundRect(12, 12, 40, 40, [8, 8, 8, 8], Color::rgb(255, 0, 0)));
    $shadow = velvet64($raster, $framebuffers, fn ($g) => $g->shadow(16, 16, 32, 32, 4, 4, Color::rgba(0, 0, 0, 0.5)));
    // pixel (14, 14): centre (14.5, 14.5), 7.778 from the arc centre (20, 20), so d = -0.222 and coverage 0.722
    $coverage = (int) round((0.5 + (8 - sqrt(2 * 5.5 ** 2))) * 255);

    expect(abs(($rect->getPixel(14, 14) & 0xFF) - $coverage))->toBeLessThanOrEqual(1)
        ->and($shadow->getPixel(32, 32) & 0xFF)->toBe(128);
})->with('engine pairings');
