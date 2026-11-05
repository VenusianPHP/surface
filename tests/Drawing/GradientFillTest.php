<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Rasterize\RasterizeDriver;
use Surface\Drawing\Velvet\VelvetGE;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\LinearGradient;

/*
 * A linear gradient fill on roundRect: exact in Velvet, the from colour in the path fallback.
 */

it('changes colour top to bottom at 90 degrees', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $buffer = $framebuffers->full(FormatSpec::rgba8(), 64, 64);
    (new VelvetGE($buffer, $raster))->frame(fn (RenderingEngine $g) => $g->roundRect(0, 0, 64, 64, [0, 0, 0, 0], Color::rgb(9, 9, 9), gradient: new LinearGradient(Color::rgb(255, 0, 0), Color::rgb(0, 0, 255), 90)));
    $red = fn (int $x, int $y): int => $buffer->getPixel($x, $y) >> 24 & 0xFF;
    $blue = fn (int $x, int $y): int => $buffer->getPixel($x, $y) >> 8 & 0xFF;

    expect($red(32, 0))->toBeGreaterThan(250)
        ->and($blue(32, 63))->toBeGreaterThan(250)
        ->and($red(32, 16))->toBeGreaterThan($red(32, 48))
        ->and($buffer->getPixel(5, 20))->toBe($buffer->getPixel(60, 20))   // the same along a row
        ->and($buffer->getPixel(32, 20) & 0xFF)->toBe(255);
})->with('engine pairings');

it('runs left to right at 0 degrees', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $buffer = $framebuffers->full(FormatSpec::rgba8(), 64, 64);
    (new VelvetGE($buffer, $raster))->frame(fn (RenderingEngine $g) => $g->roundRect(0, 0, 64, 64, [8, 8, 8, 8], Color::rgb(9, 9, 9), Color::rgb(0, 255, 0), 1, new LinearGradient(Color::rgb(255, 0, 0), Color::rgb(0, 0, 255), 0)));

    expect($buffer->getPixel(4, 32) >> 24 & 0xFF)->toBeGreaterThan($buffer->getPixel(59, 32) >> 24 & 0xFF)
        ->and($buffer->getPixel(0, 32))->toBe(0x00FF00FF);   // the border stays its own colour
})->with('engine pairings');

it('fills with the from colour in the path fallback', function () {
    $commands = (new RecordingEngine())->commandsOf(fn ($g) => $g->roundRect(0, 0, 20, 20, [2, 2, 2, 2], Color::rgb(9, 9, 9), gradient: new LinearGradient(Color::rgb(255, 0, 0), Color::rgb(0, 0, 255), 90)));

    expect($commands)->toHaveCount(1)->and($commands[0][3])->toBe(0xFF0000FF);
});
