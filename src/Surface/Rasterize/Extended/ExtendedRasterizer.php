<?php

namespace Surface\Rasterize\Extended;

use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\Scanner;
use Surface\Rasterize\Rasterizer;

final class ExtendedRasterizer extends Rasterizer
{
    public function driver(): string
    {
        return 'extended';
    }

    protected function mint(Region $clip, Edges $edges): Scanner
    {
        return new ExtendedScanner($clip, $edges);
    }
}
