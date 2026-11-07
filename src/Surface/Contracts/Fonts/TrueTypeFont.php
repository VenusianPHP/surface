<?php

namespace Surface\Contracts\Fonts;

/** A fontconfig pattern at a size, drawn through a FontDriver. */
final class TrueTypeFont implements Font
{
    public readonly float $size;

    /** @throws FontException When the size is not finite or not above zero. */
    public function __construct(
        public readonly string $pattern,
        float $size,
        private readonly FontDriver $driver,
    ) {
        if (! is_finite($size) || $size <= 0.0) {
            throw FontException::badSize($size);
        }
        $this->size = $size;
    }

    public function type(): FontType
    {
        return FontType::TrueType;
    }

    /** The same pattern and driver at another size. */
    public function at(float $size): self
    {
        return new self($this->pattern, $size, $this->driver);
    }

    public function shape(float $pixelSize, string $utf8): ShapedRun
    {
        return $this->driver->shape($this->pattern, $pixelSize, $utf8);
    }

    public function mask(string $face, float $pixelSize, int $glyph, int $style): GlyphMask
    {
        return $this->driver->mask($face, $pixelSize, $glyph, $style);
    }

    /** @return list<list<array{float, float}>> */
    public function outline(string $face, float $pixelSize, int $glyph, int $style): array
    {
        return $this->driver->outline($face, $pixelSize, $glyph, $style);
    }
}
