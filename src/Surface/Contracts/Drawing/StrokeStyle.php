<?php

namespace Surface\Contracts\Drawing;

use Surface\Contracts\Rasterize\StrokeCap;
use Surface\Contracts\Rasterize\StrokeJoin;

/**
 * How a path is stroked: SVG's stroke-width, stroke-linecap, stroke-linejoin, stroke-miterlimit, stroke-dasharray
 * and stroke-dashoffset; Canvas 2D's lineWidth, lineCap, lineJoin, miterLimit, setLineDash and lineDashOffset.
 * Lengths are in the units the path is given in.
 */
final readonly class StrokeStyle
{
    /**
     * @param  float  $width  0 or more.
     * @param  float  $miterLimit  1 or more: the longest a miter may be, as a multiple of half the width, before the corner is cut to a bevel.
     * @param  list<float>  $dash  Alternating on and off lengths, starting on; an odd count is repeated to make an even one (SVG's rule); empty is solid. Not negative, and not all zero.
     * @param  float  $dashOffset  How far into the pattern the stroke starts.
     *
     * @throws DrawingException When a number is not finite, the width is negative, the miter limit is under 1, or the dash is not a list of lengths with a positive sum.
     */
    public function __construct(
        public float $width = 1.0,
        public StrokeCap $cap = StrokeCap::BUTT,
        public StrokeJoin $join = StrokeJoin::MITER,
        public float $miterLimit = 4.0,
        public array $dash = [],
        public float $dashOffset = 0.0,
    ) {
        if (! is_finite($width) || $width < 0.0 || ! is_finite($miterLimit) || $miterLimit < 1.0 || ! is_finite($dashOffset)) {
            throw new DrawingException('A stroke style takes a width of 0 or more, a miter limit of 1 or more, and a finite dash offset.');
        }
        if ($dash !== []) {
            if (! array_is_list($dash)) {
                throw new DrawingException('A dash is a list of lengths.');
            }
            foreach ($dash as $length) {
                if ((! is_int($length) && ! is_float($length)) || ! is_finite((float) $length) || $length < 0) {
                    throw new DrawingException('A dash is a list of lengths, none negative or infinite.');
                }
            }
            if (array_sum($dash) <= 0) {
                throw new DrawingException('A dash needs a length above 0.');
            }
        }
    }
}
