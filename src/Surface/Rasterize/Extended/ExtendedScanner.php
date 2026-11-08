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

    public function roundRect(float $x0, float $y0, float $x1, float $y1, array $radii): string
    {
        try {
            return $this->scanner->roundRect($x0, $y0, $x1, $y1, $radii);
        } catch (ValueError $e) {
            throw new RasterizeException($e->getMessage(), previous: $e);
        }
    }

    public function roundRing(float $x0, float $y0, float $x1, float $y1, array $radii, float $inset): string
    {
        try {
            return $this->scanner->roundRing($x0, $y0, $x1, $y1, $radii, $inset);
        } catch (ValueError $e) {
            throw new RasterizeException($e->getMessage(), previous: $e);
        }
    }

    public function shadow(float $x0, float $y0, float $x1, float $y1, float $radius, float $blur): string
    {
        try {
            return $this->scanner->shadow($x0, $y0, $x1, $y1, $radius, $blur);
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

    public function batch(array $ops): array
    {
        try {
            return $this->scanner->batch($ops);
        } catch (ValueError $e) {
            throw new RasterizeException($e->getMessage(), previous: $e);
        }
    }

    /**
     * batch()'s ops painted into an FbBuffer through ext-fb's span sink, each at its colour.
     *
     * @param  list<list<mixed>>  $ops
     * @param  list<int>  $colours
     * @return list<Region|null> what each op painted
     */
    public function fill(array $ops, array $colours, \FbBuffer $target): array
    {
        try {
            return array_map(fn (?array $r): ?Region => $r === null ? null : new Region(...$r), $this->scanner->fill($ops, $colours, $target));
        } catch (ValueError $e) {
            throw new RasterizeException($e->getMessage(), previous: $e);
        }
    }
}
