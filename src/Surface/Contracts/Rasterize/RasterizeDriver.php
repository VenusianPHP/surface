<?php

namespace Surface\Contracts\Rasterize;

use Surface\Contracts\Framebuffers\Region;

/** Where the geometry runs. Mints rasterizers; callers never name a scanner class. */
interface RasterizeDriver
{
    /** 'native' (PHP) or 'extended' (ext-rasterize). */
    public function driver(): string;

    /** @throws RasterizeException When the clip is empty or not inside 0..65535. */
    public function rasterizer(Region $clip, Edges $edges): Rasterizer;
}
