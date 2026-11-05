<?php

namespace Surface\Text;

use Surface\Contracts\Text\ShapedRun as ShapedRunContract;

/** A shaped line of text in pixels: its glyphs in visual order, its advance width and its line metrics. */
final readonly class ShapedRun implements ShapedRunContract
{
    /** @param list<ShapedGlyph> $glyphs */
    public function __construct(
        private string $text,
        private array $glyphs,
        private float $width,
        private float $ascent,
        private float $descent,
        private float $lineHeight,
    ) {}

    /** @return list<ShapedGlyph> */
    public function glyphs(): array
    {
        return $this->glyphs;
    }

    public function width(): float
    {
        return $this->width;
    }

    /** The largest ascent over the run's faces. */
    public function ascent(): float
    {
        return $this->ascent;
    }

    /** The largest descent over the run's faces, positive. */
    public function descent(): float
    {
        return $this->descent;
    }

    public function lineHeight(): float
    {
        return $this->lineHeight;
    }

    public function text(): string
    {
        return $this->text;
    }
}
