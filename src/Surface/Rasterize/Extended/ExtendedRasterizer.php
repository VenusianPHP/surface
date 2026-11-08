<?php

namespace Surface\Rasterize\Extended;

use FbBuffer;
use RasterScanner;
use Surface\Contracts\Framebuffers\Framebuffer;
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

    /** Direct: scanned and painted in C, straight into the target's FbBuffer, when ext-fb is loaded and the target has one. */
    public function fillBatch(array $shapes, Framebuffer $target, bool $direct = true): void
    {
        $sink = $direct && RasterScanner::fills() ? $target->spanSink() : null;
        if (! $sink instanceof FbBuffer || ! $this->scanner instanceof ExtendedScanner) {
            parent::fillBatch($shapes, $target, $direct);

            return;
        }
        [$ops, $colours] = $this->lower($shapes);
        if ($ops !== []) {
            $target->paintedSpans($this->scanner->fill($ops, $colours, $sink));
        }
    }
}
