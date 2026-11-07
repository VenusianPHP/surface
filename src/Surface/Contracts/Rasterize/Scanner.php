<?php

namespace Surface\Contracts\Rasterize;

use Surface\Contracts\Framebuffers\Region;

/**
 * The row work a rasterize driver supplies: shapes already lowered to paths,
 * ellipses, rings and thin polylines, answered as span bytes (see
 * Surface\Contracts\Framebuffers\Spans) inside the clip. `native` scanners
 * work in PHP, `extended` scanners in C through ext-rasterize; both answer
 * the same bytes for the same input.
 *
 * Coordinates are finite with magnitude at most LIMIT; anything else throws
 * RasterizeException.
 */
interface Scanner
{
    /** 2³⁰: past it, the one-pixel line arithmetic would leave 64-bit integers. */
    public const float LIMIT = 1073741824.0;

    public function clip(): Region;

    public function edges(): Edges;

    /**
     * @param  list<list<float>>  $contours  Each a flat x, y, x, y… list, closed implicitly.
     */
    public function path(array $contours, FillRule $rule): string;

    public function ellipse(float $cx, float $cy, float $rx, float $ry): string;

    /** The band between radii r + stroke / 2 and r - stroke / 2. */
    public function ring(float $cx, float $cy, float $rx, float $ry, float $stroke): string;

    /**
     * A box [x0, x1) × [y0, y1) with each corner cut by a quarter circle.
     *
     * @param  array{float, float, float, float}  $radii  [tl, tr, br, bl], already held to 0..min(w, h)/2
     */
    public function roundRect(float $x0, float $y0, float $x1, float $y1, array $radii): string;

    /**
     * The band between the box and the box inset by $inset, whose radii are max(0, r − inset);
     * the whole box when the inset box is empty.
     *
     * @param  array{float, float, float, float}  $radii  [tl, tr, br, bl], already held
     */
    public function roundRing(float $x0, float $y0, float $x1, float $y1, array $radii, float $inset): string;

    /**
     * Coverage 1 − smoothstep(−blur, blur, d) at each pixel centre, d the signed distance to the box
     * rounded by $radius; hard edges keep coverage ≥ 128 at 255. A blur of 0 is the hard box.
     */
    public function shadow(float $x0, float $y0, float $x1, float $y1, float $radius, float $blur): string;

    /**
     * One-pixel lines between the floored points, every pixel once, coverage 255 whatever the edges.
     *
     * @param  list<float>  $points  A flat x, y, x, y… list.
     */
    public function polyline(array $points, bool $closed): string;
}
