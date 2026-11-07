<?php

namespace Surface\Fonts\Extended;

use Surface\Contracts\Fonts\FontDriver;
use Surface\Contracts\Fonts\FontException;
use Surface\Contracts\Fonts\GlyphMask;
use Surface\Contracts\Fonts\ShapedRun;

/**
 * TrueType through ext-fontconfig (matching and fallback), ext-harfbuzz (shaping) and ext-freetype (masks).
 * One catalog, one face cache and one shaper; their caches answer repeated shape() and mask() calls.
 */
class ExtendedFontDriver implements FontDriver
{
    private readonly FontCatalog $catalog;

    private readonly FaceCache $faces;

    private readonly Shaper $shaper;

    /** @throws FontException When ext-freetype, ext-harfbuzz or ext-fontconfig is not loaded. */
    public function __construct()
    {
        if (! function_exists('FT_Init_FreeType') || ! function_exists('hb_shape') || ! function_exists('FcFontMatch')) {
            throw FontException::extensionsMissing();
        }
        $this->catalog = new FontCatalog();
        $this->faces = new FaceCache();
        $this->shaper = new Shaper($this->catalog, $this->faces);
    }

    public function driver(): string
    {
        return 'extended';
    }

    public function shape(string $pattern, float $pixelSize, string $utf8): ShapedRun
    {
        return $this->shaper->shape($pattern, $pixelSize, $utf8);
    }

    public function mask(string $face, float $pixelSize, int $glyph, int $style): GlyphMask
    {
        return $this->face($face, $pixelSize)->mask($glyph, $style);
    }

    public function outline(string $face, float $pixelSize, int $glyph, int $style): array
    {
        return $this->face($face, $pixelSize)->outline($glyph, $style);
    }

    public function close(): void
    {
        $this->shaper->close();
        $this->faces->close();
        $this->catalog->close();
    }

    /** A face key is "<path>#<index>"; the path may hold '#', the index does not. */
    private function face(string $key, float $pixelSize): Face
    {
        $at = strrpos($key, '#');
        if ($at === false) {
            throw FontException::notFound($key);
        }

        return $this->faces->face(new FontFile(substr($key, 0, $at), (int) substr($key, $at + 1)), $pixelSize);
    }
}
