<?php

namespace Surface\Text;

use Surface\Contracts\Text\GlyphMask as GlyphMaskContract;

/**
 * A glyph's grey coverage: width*height bytes, rows top to bottom with stride width, 0..255. left and top place
 * it from the pen: its top-left is (pen x + left, baseline - top), in pixels.
 */
final readonly class GlyphMask implements GlyphMaskContract
{
    public function __construct(
        public string $coverage,
        public int $width,
        public int $height,
        public int $left,
        public int $top,
    ) {}

    public function isEmpty(): bool
    {
        return $this->width === 0 || $this->height === 0 || trim($this->coverage, "\0") === '';
    }
}
