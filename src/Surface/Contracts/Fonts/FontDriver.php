<?php

namespace Surface\Contracts\Fonts;

/**
 * Shapes and rasterises TrueType text. Repeated shape() and mask() calls answer from the driver's
 * own caches: the engine base calls mask() every frame to box the text.
 */
interface FontDriver
{
    public function driver(): string;

    public function shape(string $pattern, float $pixelSize, string $utf8): ShapedRun;

    public function mask(string $face, float $pixelSize, int $glyph, int $style): GlyphMask;

    /**
     * The glyph's contours in pixels from the glyph origin, y down, curves flattened to within 0.1 px;
     * empty for a glyph with no outline.
     *
     * @return list<list<array{float, float}>>
     */
    public function outline(string $face, float $pixelSize, int $glyph, int $style): array;

    public function close(): void;
}
