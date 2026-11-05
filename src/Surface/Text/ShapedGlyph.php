<?php

namespace Surface\Text;

use Surface\Contracts\Text\ShapedGlyph as ShapedGlyphContract;

/**
 * One positioned glyph. x and y are the pen plus HarfBuzz's offsets, in pixels from the run's origin on the
 * baseline (y grows down). cluster is the byte offset in the UTF-8 text the glyph came from. oblique and
 * embolden ask for a synthetic italic or bold mask.
 */
final readonly class ShapedGlyph implements ShapedGlyphContract
{
    public function __construct(
        public Face $face,
        public int $glyph,
        public float $x,
        public float $y,
        public float $advance,
        public int $cluster,
        public bool $oblique = false,
        public bool $embolden = false,
    ) {}

    public function mask(): GlyphMask
    {
        return $this->face->mask($this->glyph, $this->oblique, $this->embolden);
    }
}
