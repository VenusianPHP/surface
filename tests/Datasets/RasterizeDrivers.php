<?php

declare(strict_types=1);

use Surface\Rasterize\Extended\ExtendedRasterizeDriver;
use Surface\Rasterize\Native\NativeRasterizeDriver;

/** Every rasterize driver this PHP can run: native always, extended when ext-rasterize is loaded. */
dataset('rasterize drivers', function () {
    yield 'native' => [new NativeRasterizeDriver()];

    if (class_exists(RasterScanner::class)) {
        yield 'extended' => [new ExtendedRasterizeDriver()];
    }
});
