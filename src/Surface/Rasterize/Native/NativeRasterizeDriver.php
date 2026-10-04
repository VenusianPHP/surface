<?php

namespace Surface\Rasterize\Native;

use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\RasterizeDriver;
use Surface\Contracts\Rasterize\Rasterizer;

/** The geometry in PHP. Always available. */
class NativeRasterizeDriver implements RasterizeDriver
{
    public function driver(): string
    {
        return 'native';
    }

    public function rasterizer(Region $clip, Edges $edges): Rasterizer
    {
        return new NativeRasterizer($clip, $edges);
    }
}
