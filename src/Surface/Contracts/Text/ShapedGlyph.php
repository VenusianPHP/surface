<?php

namespace Surface\Contracts\Text;

/**
 * One positioned glyph. x and y are pixels from the run's origin on the baseline (y grows down); cluster is the
 * byte offset in the UTF-8 text the glyph came from.
 */
interface ShapedGlyph
{
    public int $glyph { get; }

    public float $x { get; }

    public float $y { get; }

    public float $advance { get; }

    public int $cluster { get; }

    public function mask(): GlyphMask;
}
