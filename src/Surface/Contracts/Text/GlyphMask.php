<?php

namespace Surface\Contracts\Text;

/**
 * A glyph's grey coverage: width*height bytes, rows top to bottom with stride width, 0..255. Its top-left is
 * (pen x + left, baseline - top), in pixels.
 */
interface GlyphMask
{
    public string $coverage { get; }

    public int $width { get; }

    public int $height { get; }

    public int $left { get; }

    public int $top { get; }

    /** No size, or no byte above 0. */
    public function isEmpty(): bool;
}
