<?php

namespace Surface\Contracts\Rasterize;

use Surface\Contracts\Framebuffers\Region;

/**
 * Shapes into coverage spans. Coordinates are float target pixels with
 * integer coordinates on pixel edges; points are [x, y] pairs. Every method
 * answers span bytes inside the clip (Surface\Contracts\Framebuffers\Spans),
 * ready for Framebuffer::paintSpans(). A zero or negative size, radius or
 * stroke answers no spans.
 *
 * Every number is finite with magnitude at most LIMIT; anything else, or a
 * point that is not [x, y], throws RasterizeException.
 */
interface Rasterizer
{
    /** 2²⁴: room for a shape's derived points (strokes, miters) under Scanner::LIMIT. */
    public const float LIMIT = 16777216.0;

    /** 'native' (geometry in PHP) or 'extended' (geometry in C, ext-rasterize). */
    public function driver(): string;

    public function edges(): Edges;

    public function clip(): Region;

    public function fillRect(float $x, float $y, float $width, float $height): string;

    /** Hard edges and a stroke of at most 1: the one-pixel outline through the floored corners. */
    public function strokeRect(float $x, float $y, float $width, float $height, float $stroke = 1.0): string;

    /** Hard edges and a stroke of at most 1: the one-pixel line between the floored endpoints. Butt ends otherwise. */
    public function line(float $x0, float $y0, float $x1, float $y1, float $stroke = 1.0): string;

    /**
     * Hard edges and a stroke of at most 1: one-pixel lines through the floored points.
     * Otherwise the outline: butt ends, miter corners, bevel past a miter of 4 half-strokes.
     *
     * @param  list<array{float, float}>  $points
     */
    public function polyline(array $points, float $stroke = 1.0, bool $closed = false): string;

    public function fillTriangle(float $x0, float $y0, float $x1, float $y1, float $x2, float $y2): string;

    /**
     * Any polygon, self-intersecting included.
     *
     * @param  list<array{float, float}>  $points
     */
    public function fillPolygon(array $points, FillRule $rule = FillRule::NON_ZERO): string;

    /**
     * Several contours as one shape: holes, unions.
     *
     * @param  list<list<array{float, float}>>  $contours
     */
    public function fillPath(array $contours, FillRule $rule = FillRule::NON_ZERO): string;

    public function fillEllipse(float $cx, float $cy, float $rx, float $ry): string;

    public function strokeEllipse(float $cx, float $cy, float $rx, float $ry, float $stroke = 1.0): string;
}
