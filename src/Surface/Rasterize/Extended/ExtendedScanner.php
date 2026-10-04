<?php

namespace Surface\Rasterize\Extended;

use RasterScanner;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\FillRule;
use Surface\Contracts\Rasterize\RasterizeException;
use Surface\Contracts\Rasterize\Scanner;
use ValueError;

/**
 * The row work in C: one RasterScanner. Every call is one call into
 * ext-rasterize; what it refuses with ValueError is rethrown as RasterizeException.
 */
final class ExtendedScanner implements Scanner
{
    private RasterScanner $scanner;

    public function __construct(
        private readonly Region $clip,
        private readonly Edges $edges,
    ) {
        try {
            $this->scanner = new RasterScanner($clip->x, $clip->y, $clip->width, $clip->height, $edges === Edges::ANTIALIASED);
        } catch (ValueError $e) {
            throw new RasterizeException($e->getMessage(), previous: $e);
        }
    }

    public function clip(): Region
    {
        return $this->clip;
    }

    public function edges(): Edges
    {
        return $this->edges;
    }

    public function path(array $contours, FillRule $rule): string
    {
        try {
            return $this->scanner->path($contours, $rule === FillRule::EVEN_ODD ? \RASTER_EVEN_ODD : \RASTER_NON_ZERO);
        } catch (ValueError $e) {
            throw new RasterizeException($e->getMessage(), previous: $e);
        }
    }

    public function ellipse(float $cx, float $cy, float $rx, float $ry): string
    {
        try {
            return $this->scanner->ellipse($cx, $cy, $rx, $ry);
        } catch (ValueError $e) {
            throw new RasterizeException($e->getMessage(), previous: $e);
        }
    }

    public function ring(float $cx, float $cy, float $rx, float $ry, float $stroke): string
    {
        try {
            return $this->scanner->ring($cx, $cy, $rx, $ry, $stroke);
        } catch (ValueError $e) {
            throw new RasterizeException($e->getMessage(), previous: $e);
        }
    }

    public function polyline(array $points, bool $closed): string
    {
        try {
            return $this->scanner->polyline($points, $closed);
        } catch (ValueError $e) {
            throw new RasterizeException($e->getMessage(), previous: $e);
        }
    }
}
