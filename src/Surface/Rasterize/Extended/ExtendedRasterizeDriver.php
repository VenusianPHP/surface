<?php

namespace Surface\Rasterize\Extended;

use RasterScanner;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\RasterizeDriver;
use Surface\Contracts\Rasterize\RasterizeException;
use Surface\Contracts\Rasterize\Rasterizer;

/** The geometry in C, through ext-rasterize. */
class ExtendedRasterizeDriver implements RasterizeDriver
{
    /** @throws RasterizeException When ext-rasterize is not loaded in this PHP. */
    public function __construct()
    {
        if (! class_exists(RasterScanner::class)) {
            throw RasterizeException::extensionMissing();
        }
    }

    public function driver(): string
    {
        return 'extended';
    }

    public function rasterizer(Region $clip, Edges $edges): Rasterizer
    {
        return new ExtendedRasterizer($clip, $edges);
    }
}
