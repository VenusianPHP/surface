<?php

namespace Surface\Contracts\Fonts;

/** One shaped glyph. $face is "<path>#<index>"; x/y are pixels from the run origin, y down. */
final readonly class ShapedGlyph
{
    /** Slanted because the matched face is not italic. */
    public const int OBLIQUE = 1;

    /** Thickened because the matched face is not bold. */
    public const int EMBOLDEN = 2;

    public function __construct(
        public string $face,
        public int $glyph,
        public float $x,
        public float $y,
        public float $advance,
        public int $cluster,
        public int $style,
    ) {}
}
