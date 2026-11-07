<?php

namespace Surface\Contracts\Fonts;

/** One line of shaped text, in pixels. */
final readonly class ShapedRun
{
    /** @param list<ShapedGlyph> $glyphs */
    public function __construct(
        public string $text,
        public array $glyphs,
        public float $advance,
        public float $ascent,
        public float $descent,
        public float $lineHeight,
    ) {}
}
