<?php

namespace Surface\Contracts\Rasterize;

use Surface\Contracts\Framebuffers\Framebuffer;
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

    /**
     * A rect with each corner cut by its own radius, held to 0..min(w, h)/2.
     *
     * @param  array{float, float, float, float}  $radii  [tl, tr, br, bl]
     */
    public function fillRoundRect(float $x, float $y, float $width, float $height, array $radii): string;

    /**
     * A band $stroke wide inside the rounded rect; the whole rect when it reaches the middle.
     *
     * @param  array{float, float, float, float}  $radii  [tl, tr, br, bl]
     */
    public function strokeRoundRect(float $x, float $y, float $width, float $height, array $radii, float $stroke): string;

    /** A blurred rounded box: coverage 1 − smoothstep(−blur, blur, d) at each pixel centre. A blur ≤ 0 is the hard box. */
    public function shadow(float $x, float $y, float $width, float $height, float $radius, float $blur): string;

    /**
     * Many shapes painted in order, their geometry scanned in one call: each shape is a drawing command
     * (Surface\Drawing\RenderingEngine) of kind path, polyline, ellipse, ring, roundrect, roundring or shadow, whose
     * entries after its colour (the clip) are not read; the rasterizer's own clip cuts. Each shape paints what the
     * method of its kind would answer, through $target->paintSpans() at its colour. A bad shape throws before any
     * shape of the batch is painted. With $direct, a rasterizer may paint straight into $target->spanSink() (the
     * extended one does, through ext-fb), the same pixels and damage; without, every shape goes through paintSpans().
     *
     * @param  list<array<int, mixed>>  $shapes
     */
    public function fillBatch(array $shapes, Framebuffer $target, bool $direct = true): void;
}
