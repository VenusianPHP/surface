<?php

declare(strict_types=1);

use Surface\Framebuffers\Extended\ExtendedFramebufferDriver;
use Surface\Framebuffers\Native\NativeFramebufferDriver;
use Surface\Rasterize\Extended\ExtendedRasterizeDriver;
use Surface\Rasterize\Native\NativeRasterizeDriver;

/** Every pairing of a rasterize driver with a framebuffer driver this PHP can run: what a software engine can be built from. */
dataset('engine pairings', function () {
    $rasterize = ['native' => new NativeRasterizeDriver()];
    $framebuffers = ['native' => new NativeFramebufferDriver()];
    if (class_exists(RasterScanner::class)) {
        $rasterize['extended'] = new ExtendedRasterizeDriver();
    }
    if (class_exists(FbBuffer::class)) {
        $framebuffers['extended'] = new ExtendedFramebufferDriver();
    }

    foreach ($rasterize as $r => $raster) {
        foreach ($framebuffers as $f => $framebuffer) {
            yield "{$r} geometry, {$f} bytes" => [$raster, $framebuffer];
        }
    }
});
