<?php

namespace Surface\Contracts\Fonts;

/**
 * Grey coverage 0..255, rows top-down, $width bytes per row. $left and $top are measured from the
 * glyph origin; $top is up from the baseline.
 */
final readonly class GlyphMask
{
    public function __construct(
        public string $coverage,
        public int $width,
        public int $height,
        public int $left,
        public int $top,
    ) {}
}
