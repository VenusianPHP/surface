<?php

namespace Surface\Contracts\Text;

/** A shaped line of text in device pixels: glyphs in visual order, advance width and line metrics. */
interface ShapedRun
{
    /** @return list<ShapedGlyph> */
    public function glyphs(): array;

    public function width(): float;

    public function ascent(): float;

    /** Positive, below the baseline. */
    public function descent(): float;

    public function lineHeight(): float;

    public function text(): string;
}
