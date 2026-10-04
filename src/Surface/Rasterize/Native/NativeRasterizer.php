<?php

namespace Surface\Rasterize\Native;

use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\Scanner;
use Surface\Rasterize\Rasterizer;

final class NativeRasterizer extends Rasterizer
{
    public function driver(): string
    {
        return 'native';
    }

    protected function mint(Region $clip, Edges $edges): Scanner
    {
        return new NativeScanner($clip, $edges);
    }
}
