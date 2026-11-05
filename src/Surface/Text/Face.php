<?php

namespace Surface\Text;

use FT_Face;
use FT_Matrix;
use hb_font_t;
use RuntimeException;

/**
 * One font file at one pixel size: line metrics, glyph lookup and grey glyph masks, unhinted. Masks are kept in
 * a least-recently-used cache. The FaceCache that made the face owns its handles and releases them.
 */
class Face
{
    /** Masks kept per face; the least recently used goes first. */
    public const int MASKS = 2048;

    /** @var array<int, GlyphMask> */
    private array $masks = [];

    private ?hb_font_t $hb = null;

    public function __construct(
        public readonly FontFile $file,
        public readonly float $pixelSize,
        private readonly FT_Face $face,
    ) {}

    /** Pixels above the baseline. */
    public function ascent(): float
    {
        return $this->face->size->metrics->ascender / 64;
    }

    /** Pixels below the baseline, positive. */
    public function descent(): float
    {
        return -$this->face->size->metrics->descender / 64;
    }

    public function lineHeight(): float
    {
        return $this->face->size->metrics->height / 64;
    }

    /** 0 (.notdef) when the face has no glyph for the codepoint. */
    public function glyphIndex(int $codepoint): int
    {
        return FT_Get_Char_Index($this->face, $codepoint);
    }

    /**
     * The glyph's grey coverage, unhinted. oblique slants it 0.2 and embolden thickens it, for a style the file
     * lacks (synthetic italic and bold); each variant is cached apart.
     */
    public function mask(int $glyphIndex, bool $oblique = false, bool $embolden = false): GlyphMask
    {
        $key = ($glyphIndex << 2) | ($oblique ? 1 : 0) | ($embolden ? 2 : 0);
        if (isset($this->masks[$key])) {
            $mask = $this->masks[$key];
            unset($this->masks[$key]);   // move it to the young end

            return $this->masks[$key] = $mask;
        }
        if (count($this->masks) >= self::MASKS) {
            unset($this->masks[array_key_first($this->masks)]);
        }

        return $this->masks[$key] = $this->render($glyphIndex, $oblique, $embolden);
    }

    public function isItalic(): bool
    {
        return ($this->face->style_flags & FT_STYLE_FLAG_ITALIC) !== 0;
    }

    public function isBold(): bool
    {
        return ($this->face->style_flags & FT_STYLE_FLAG_BOLD) !== 0;
    }

    public function ftFace(): FT_Face
    {
        return $this->face;
    }

    public function hbFont(): hb_font_t
    {
        return $this->hb ??= hb_ft_font_create_referenced($this->face);
    }

    /** Release the HarfBuzz font; the FaceCache releases the FT_Face after. */
    public function close(): void
    {
        if (! is_null($this->hb)) {
            hb_font_destroy($this->hb);
            $this->hb = null;
        }
        $this->masks = [];
    }

    private function render(int $glyphIndex, bool $oblique, bool $embolden): GlyphMask
    {
        if ($oblique) {
            $shear = new FT_Matrix();
            $shear->xx = 0x10000;
            $shear->xy = (int) round(0.2 * 0x10000);
            $shear->yy = 0x10000;
            FT_Set_Transform($this->face, $shear, null);
        }
        $loaded = FT_Load_Glyph($this->face, $glyphIndex, FT_LOAD_NO_HINTING);
        if ($oblique) {
            FT_Set_Transform($this->face, null, null);
        }
        if ($loaded !== 0) {
            throw new RuntimeException("FreeType could not load glyph {$glyphIndex} of {$this->file->path}.");
        }
        $slot = $this->face->glyph;
        if ($embolden) {
            // FreeType's own FT_GlyphSlot_Embolden strength: a 24th of the em, in 26.6.
            FT_Outline_Embolden($slot->outline, (int) round($this->pixelSize * 64 / 24));
        }
        if (FT_Render_Glyph($slot, FT_RENDER_MODE_NORMAL) !== 0) {
            throw new RuntimeException("FreeType could not render glyph {$glyphIndex} of {$this->file->path}.");
        }
        $bitmap = $slot->bitmap;
        $width = $bitmap->width;
        $rows = $bitmap->rows;
        $pitch = $bitmap->pitch;
        if ($width === 0 || $rows === 0) {
            return new GlyphMask('', 0, 0, $slot->bitmap_left, $slot->bitmap_top);
        }
        // Rows are pitch bytes apart and pitch may exceed width; a negative pitch runs bottom up.
        $coverage = '';
        for ($row = 0; $row < $rows; $row++) {
            $from = $pitch >= 0 ? $row * $pitch : ($rows - 1 - $row) * -$pitch;
            $coverage .= substr($bitmap->buffer, $from, $width);
        }

        return new GlyphMask($coverage, $width, $rows, $slot->bitmap_left, $slot->bitmap_top);
    }
}
