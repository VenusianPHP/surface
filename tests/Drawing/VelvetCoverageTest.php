<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Rasterize\RasterizeDriver;
use Surface\Drawing\Velvet\VelvetGE;
use Surface\NutsAndBolts\Color;

/*
 * Velvet blends a coverage mask exactly, per pixel.
 */

it('blends each byte as that much of the colour', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $buffer = $framebuffers->full(FormatSpec::rgba8(), 8, 2);
    (new VelvetGE($buffer, $raster))->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0))->coverage("\x00\x80\xFF\x40", 4, 1, 2, 1, Color::rgb(255, 255, 255)));
    $red = fn (int $x, int $y): int => $buffer->getPixel($x, $y) >> 24 & 0xFF;

    expect($red(2, 1))->toBe(0)
        ->and(abs($red(3, 1) - 128))->toBeLessThanOrEqual(1)
        ->and($red(4, 1))->toBe(255)
        ->and(abs($red(5, 1) - 64))->toBeLessThanOrEqual(1)
        ->and($red(3, 0))->toBe(0);
})->with('engine pairings');
