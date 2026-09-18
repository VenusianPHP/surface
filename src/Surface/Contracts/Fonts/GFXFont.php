<?php

namespace Surface\Contracts\Fonts;

/**
 * A bitmap face: the glyph table and its bytes, no drawing. Adafruit GFX 1bpp
 * row-major, LVGL 1bpp / 4bpp with a reserved glyph 0, or the classic 5x7
 * column-major table. A subclass sets properties; the readers resolve the
 * encoding once and cache it. Readers never throw: an unknown code is null,
 * a byte past the table is 0.
 */
abstract class GFXFont
{
    protected int $first = 0x20;

    protected int $last = 0x7E;

    /** Line height. */
    protected int $y_advance = 8;

    /** Classic 5x7: five column bytes per code, no glyph table. */
    protected bool $column_major = false;

    /** 1 = bits, 4 = anti-aliased nibbles (LVGL). */
    protected int $bits_per_pixel = 1;

    /** null detects from the glyph table. */
    protected ?FontEncoding $encoding = null;

    /** null detects from the offsets. */
    protected ?YOffsetMode $y_offset_mode = null;

    /** A 4bpp nibble at or above this is ink; 0..15. */
    protected int $alpha_threshold = 8;

    /** @var list<int> */
    protected array $bitmaps = [];

    /** @var list<array{int, int, int, int, int, int}> [bitmap_offset, width, height, x_advance, x_offset, y_offset] */
    protected array $glyphs = [];

    private ?FontEncoding $resolved_encoding = null;

    private ?YOffsetMode $resolved_y_offset_mode = null;

    private ?int $cap_height = null;

    public function first(): int
    {
        return $this->first;
    }

    public function last(): int
    {
        return $this->last;
    }

    public function lineHeight(): int
    {
        return $this->y_advance;
    }

    public function isColumnMajor(): bool
    {
        return $this->column_major;
    }

    public function bitsPerPixel(): int
    {
        return $this->bits_per_pixel;
    }

    public function alphaThreshold(): int
    {
        return $this->alpha_threshold;
    }

    public function encoding(): FontEncoding
    {
        return $this->resolved_encoding ??= $this->encoding ?? $this->detectEncoding();
    }

    public function yOffsetMode(): YOffsetMode
    {
        return $this->resolved_y_offset_mode ??= $this->y_offset_mode ?? $this->detectYOffsetMode();
    }

    /** Tallest of A..Z; the line height for a face without capitals. */
    public function capHeight(): int
    {
        if (! is_null($this->cap_height)) {
            return $this->cap_height;
        }
        $max = 0;
        for ($code = max($this->first, 0x41); $code <= min($this->last, 0x5A); $code++) {
            $glyph = $this->glyph($code);
            if (! is_null($glyph) && $glyph->height > $max) {
                $max = $glyph->height;
            }
        }

        return $this->cap_height = $max > 0 ? $max : $this->y_advance;
    }

    /** Whether the face carries drawable bytes — an empty scaffold does not. */
    public function hasBitmapData(): bool
    {
        return $this->bitmaps !== [];
    }

    public function byte(int $offset): int
    {
        return $this->bitmaps[$offset] ?? 0;
    }

    /** Null outside first..last or when nothing describes the code. */
    public function glyph(int $code): ?Glyph
    {
        if ($code < $this->first || $code > $this->last) {
            return null;
        }
        $index = $code - $this->first + ($this->encoding() === FontEncoding::LVGL ? 1 : 0);
        if (isset($this->glyphs[$index])) {
            [$offset, $width, $height, $x_advance, $x_offset, $y_offset] = $this->glyphs[$index];

            return new Glyph($offset, $width, $height, $x_advance, $x_offset, $y_offset);
        }
        if ($this->column_major && $this->bitmaps !== []) {
            return new Glyph($code * 5, 5, 8, 6, 0, 0);
        }

        return null;
    }

    /** LVGL conversions carry an all-zero glyph 0 and then one entry per code. */
    private function detectEncoding(): FontEncoding
    {
        $range = $this->last - $this->first + 1;

        return $this->reservedGlyph0() && count($this->glyphs) >= $range + 1 ? FontEncoding::LVGL : FontEncoding::ADAFRUIT;
    }

    private function reservedGlyph0(): bool
    {
        $g = $this->glyphs[0] ?? null;

        return is_array($g) && count($g) >= 6 && $g[0] === 0 && $g[1] === 0 && $g[2] === 0 && $g[3] === 0 && $g[4] === 0 && $g[5] === 0;
    }

    /** Adafruit is baseline-relative. An LVGL face with only non-negative offsets measures from the line bottom. */
    private function detectYOffsetMode(): YOffsetMode
    {
        if ($this->encoding() !== FontEncoding::LVGL) {
            return YOffsetMode::RAW;
        }
        $end = min(count($this->glyphs), 97);
        for ($i = 1; $i < $end; $i++) {
            $g = $this->glyphs[$i] ?? null;
            if (is_array($g) && count($g) >= 6 && $g[5] < 0) {
                return YOffsetMode::RAW;
            }
        }

        return YOffsetMode::LINE;
    }
}
