<?php

namespace Surface\Fonts\Extended;

use FT_Face;
use FT_GlyphSlot;
use FT_Matrix;
use FT_Outline_Funcs;
use FT_Vector;
use hb_font_t;
use Surface\Contracts\Fonts\FontException;
use Surface\Contracts\Fonts\GlyphMask;
use Surface\Contracts\Fonts\ShapedGlyph;

/**
 * One font file at one pixel size: line metrics, glyph lookup, and grey glyph masks and flattened outlines,
 * unhinted. Masks and outlines are kept in least-recently-used caches. The FaceCache that made the face owns its handles and releases them.
 */
class Face
{
    /** Masks kept per face; the least recently used goes first. */
    public const int MASKS = 2048;

    /** Outline curves are cut until they stray from their chords by at most this many pixels. */
    public const float FLATNESS = 0.1;

    /** @var array<int, GlyphMask> */
    private array $masks = [];

    /** @var array<int, list<list<array{float, float}>>> */
    private array $outlines = [];

    private ?hb_font_t $hb = null;

    public function __construct(
        public readonly FontFile $file,
        public readonly float $pixelSize,
        private readonly FT_Face $face,
    ) {}

    /** "<path>#<index>", the face key of a ShapedGlyph. */
    public function key(): string
    {
        return "{$this->file->path}#{$this->file->index}";
    }

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
     * The glyph's grey coverage, unhinted. ShapedGlyph::OBLIQUE slants it 0.2 and EMBOLDEN thickens it, for a
     * style the file lacks (synthetic italic and bold); each variant is cached apart.
     */
    public function mask(int $glyphIndex, int $style = 0): GlyphMask
    {
        $key = ($glyphIndex << 2) | ($style & 3);
        if (isset($this->masks[$key])) {
            $mask = $this->masks[$key];
            unset($this->masks[$key]);   // move it to the young end

            return $this->masks[$key] = $mask;
        }
        if (count($this->masks) >= self::MASKS) {
            unset($this->masks[array_key_first($this->masks)]);
        }

        return $this->masks[$key] = $this->render($glyphIndex, ($style & ShapedGlyph::OBLIQUE) !== 0, ($style & ShapedGlyph::EMBOLDEN) !== 0);
    }

    /**
     * The glyph's contours in pixels from its origin, y down, curves flattened to within FLATNESS, with the same
     * synthetic slant and weight as mask(). Empty for a glyph with no outline (a bitmap glyph, or a space).
     *
     * @return list<list<array{float, float}>>
     */
    public function outline(int $glyphIndex, int $style = 0): array
    {
        $key = ($glyphIndex << 2) | ($style & 3);
        if (isset($this->outlines[$key])) {
            $outline = $this->outlines[$key];
            unset($this->outlines[$key]);

            return $this->outlines[$key] = $outline;
        }
        if (count($this->outlines) >= self::MASKS) {
            unset($this->outlines[array_key_first($this->outlines)]);
        }

        return $this->outlines[$key] = $this->walk($glyphIndex, $style);
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
        $this->outlines = [];
    }

    private function render(int $glyphIndex, bool $oblique, bool $embolden): GlyphMask
    {
        $slot = $this->load($glyphIndex, $oblique, $embolden);
        if (FT_Render_Glyph($slot, FT_RENDER_MODE_NORMAL) !== 0) {
            throw FontException::library("FreeType could not render glyph {$glyphIndex} of {$this->file->path}");
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

    /** @return list<list<array{float, float}>> */
    private function walk(int $glyphIndex, int $style): array
    {
        $slot = $this->load($glyphIndex, ($style & ShapedGlyph::OBLIQUE) !== 0, ($style & ShapedGlyph::EMBOLDEN) !== 0);
        if ($slot->format !== 0x6F75746C) {   // FT_GLYPH_FORMAT_OUTLINE, FT_IMAGE_TAG 'o','u','t','l'
            return [];
        }
        $contours = [];
        $pen = [0.0, 0.0];
        $px = fn (FT_Vector $v): array => [$v->x / 64, -$v->y / 64];
        $funcs = new FT_Outline_Funcs();
        $funcs->move_to = function (FT_Vector $to) use (&$contours, &$pen, $px): int {
            $contours[] = [$pen = $px($to)];

            return 0;
        };
        $funcs->line_to = function (FT_Vector $to) use (&$contours, &$pen, $px): int {
            $contours[array_key_last($contours)][] = $pen = $px($to);

            return 0;
        };
        $funcs->conic_to = function (FT_Vector $control, FT_Vector $to) use (&$contours, &$pen, $px): int {
            $last = array_key_last($contours);
            self::quadratic($contours[$last], $pen, $px($control), $pen = $px($to));

            return 0;
        };
        $funcs->cubic_to = function (FT_Vector $c1, FT_Vector $c2, FT_Vector $to) use (&$contours, &$pen, $px): int {
            $last = array_key_last($contours);
            self::cubic($contours[$last], $pen, $px($c1), $px($c2), $pen = $px($to));

            return 0;
        };
        if (FT_Outline_Decompose($slot->outline, $funcs, null) !== 0) {
            throw FontException::library("FreeType could not walk glyph {$glyphIndex} of {$this->file->path}");
        }

        return $contours;
    }

    /**
     * Appends the quadratic from $a through $b to $c as points after $a, halving until it is flat: its farthest
     * stray from the chord is |a − 2b + c| / 4.
     *
     * @param  list<array{float, float}>  $points
     */
    private static function quadratic(array &$points, array $a, array $b, array $c): void
    {
        if (hypot($a[0] - 2 * $b[0] + $c[0], $a[1] - 2 * $b[1] + $c[1]) / 4 <= self::FLATNESS) {
            $points[] = $c;

            return;
        }
        $ab = [($a[0] + $b[0]) / 2, ($a[1] + $b[1]) / 2];
        $bc = [($b[0] + $c[0]) / 2, ($b[1] + $c[1]) / 2];
        $mid = [($ab[0] + $bc[0]) / 2, ($ab[1] + $bc[1]) / 2];
        self::quadratic($points, $a, $ab, $mid);
        self::quadratic($points, $mid, $bc, $c);
    }

    /**
     * As quadratic(), for a cubic: its stray is at most 3/4 of the larger second difference.
     *
     * @param  list<array{float, float}>  $points
     */
    private static function cubic(array &$points, array $a, array $b, array $c, array $d): void
    {
        $stray = max(hypot($a[0] - 2 * $b[0] + $c[0], $a[1] - 2 * $b[1] + $c[1]), hypot($b[0] - 2 * $c[0] + $d[0], $b[1] - 2 * $c[1] + $d[1])) * 3 / 4;
        if ($stray <= self::FLATNESS) {
            $points[] = $d;

            return;
        }
        $ab = [($a[0] + $b[0]) / 2, ($a[1] + $b[1]) / 2];
        $bc = [($b[0] + $c[0]) / 2, ($b[1] + $c[1]) / 2];
        $cd = [($c[0] + $d[0]) / 2, ($c[1] + $d[1]) / 2];
        $abc = [($ab[0] + $bc[0]) / 2, ($ab[1] + $bc[1]) / 2];
        $bcd = [($bc[0] + $cd[0]) / 2, ($bc[1] + $cd[1]) / 2];
        $mid = [($abc[0] + $bcd[0]) / 2, ($abc[1] + $bcd[1]) / 2];
        self::cubic($points, $a, $ab, $abc, $mid);
        self::cubic($points, $mid, $bcd, $cd, $d);
    }

    /** The glyph loaded unhinted into the face's slot, slanted and thickened as asked. */
    private function load(int $glyphIndex, bool $oblique, bool $embolden): FT_GlyphSlot
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
            throw FontException::library("FreeType could not load glyph {$glyphIndex} of {$this->file->path}");
        }
        $slot = $this->face->glyph;
        if ($embolden) {
            // FreeType's own FT_GlyphSlot_Embolden strength: a 24th of the em, in 26.6.
            FT_Outline_Embolden($slot->outline, (int) round($this->pixelSize * 64 / 24));
        }

        return $slot;
    }
}
