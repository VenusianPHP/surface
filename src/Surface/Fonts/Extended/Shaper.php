<?php

namespace Surface\Fonts\Extended;

use hb_buffer_t;
use Surface\Contracts\Fonts\ShapedGlyph;
use Surface\Contracts\Fonts\ShapedRun;
use IntlBreakIterator;
use IntlChar;

/**
 * UTF-8 text to positioned glyphs. Each grapheme goes to the style's primary face when the face has every one of
 * its codepoints, else to the first fontconfig fallback that has them, else to the primary face (whose .notdef
 * shows). Each stretch of one face is shaped by HarfBuzz with the whole text as context. Invalid UTF-8 bytes go
 * to the primary face, which HarfBuzz gives its replacement glyph. Bidi is not done: each stretch is shaped in
 * the direction HarfBuzz guesses.
 */
class Shaper
{
    /** Shapes kept; the least recently used goes first. */
    public const int RUNS = 512;

    private ?hb_buffer_t $buffer = null;

    /** @var array<string, ShapedRun> */
    private array $runs = [];

    public function __construct(
        private readonly FontCatalog $catalog,
        private readonly FaceCache $faces,
    ) {}

    public function shape(string $pattern, float $pixelSize, string $utf8): ShapedRun
    {
        $key = "{$pattern}\0{$pixelSize}\0{$utf8}";
        if (isset($this->runs[$key])) {
            $run = $this->runs[$key];
            unset($this->runs[$key]);

            return $this->runs[$key] = $run;
        }
        if (count($this->runs) >= self::RUNS) {
            unset($this->runs[array_key_first($this->runs)]);
        }

        return $this->runs[$key] = $this->shapeNew($utf8, $pattern, $pixelSize);
    }

    /** Release the HarfBuzz buffer. The catalog and face cache close on their own. */
    public function close(): void
    {
        if (! is_null($this->buffer)) {
            hb_buffer_destroy($this->buffer);
            $this->buffer = null;
        }
        $this->runs = [];
    }

    private function shapeNew(string $utf8, string $pattern, float $pixels): ShapedRun
    {
        [$slant, $weight] = $this->catalog->requested($pattern);
        $primary = $this->faces->face($this->catalog->match($pattern), $pixels);
        $used = [spl_object_id($primary) => $primary];
        $glyphs = [];
        $pen = 0.0;

        foreach ($this->stretches($utf8, $pattern, $primary, $pixels) as [$face, $offset, $length]) {
            $used[spl_object_id($face)] = $face;
            $style = ($slant > 0 && ! $face->isItalic() ? ShapedGlyph::OBLIQUE : 0)
                | ($weight >= FC_WEIGHT_BOLD && ! $face->isBold() ? ShapedGlyph::EMBOLDEN : 0);
            $buffer = $this->buffer ??= hb_buffer_create();
            hb_buffer_reset($buffer);
            hb_buffer_add_utf8($buffer, $utf8, strlen($utf8), $offset, $length);
            hb_buffer_guess_segment_properties($buffer);
            hb_buffer_set_cluster_level($buffer, HB_BUFFER_CLUSTER_LEVEL_MONOTONE_CHARACTERS);
            hb_shape($face->hbFont(), $buffer, null, 0);
            $count = 0;
            $infos = hb_buffer_get_glyph_infos($buffer, $count);
            $positions = hb_buffer_get_glyph_positions($buffer, $count);
            foreach ($infos as $i => $info) {
                $at = $positions[$i];
                $advance = $at->x_advance / 64;
                $glyphs[] = new ShapedGlyph($face->key(), $info->codepoint, $pen + $at->x_offset / 64, -$at->y_offset / 64, $advance, $info->cluster, $style);
                $pen += $advance;
            }
        }

        return new ShapedRun(
            $utf8,
            $glyphs,
            $pen,
            max(array_map(fn (Face $f) => $f->ascent(), $used)),
            max(array_map(fn (Face $f) => $f->descent(), $used)),
            max(array_map(fn (Face $f) => $f->lineHeight(), $used)),
        );
    }

    /**
     * The text cut into stretches of one face each, in logical order.
     *
     * @return list<array{Face, int, int}> face, byte offset, byte length
     */
    private function stretches(string $utf8, string $pattern, Face $primary, float $pixels): array
    {
        $stretches = [];
        foreach (self::graphemes($utf8) as [$offset, $length, $valid]) {
            $face = $valid ? $this->faceFor(substr($utf8, $offset, $length), $pattern, $primary, $pixels) : $primary;
            $last = array_key_last($stretches);
            if (! is_null($last) && $stretches[$last][0] === $face) {
                $stretches[$last][2] += $length;
            } else {
                $stretches[] = [$face, $offset, $length];
            }
        }

        return $stretches;
    }

    private function faceFor(string $grapheme, string $pattern, Face $primary, float $pixels): Face
    {
        $codepoints = array_map(IntlChar::ord(...), mb_str_split($grapheme, 1, 'UTF-8'));
        $has = fn (Face $face): bool => array_all($codepoints, fn (int $cp) => $face->glyphIndex($cp) !== 0 || self::invisible($cp));
        if ($has($primary)) {
            return $primary;
        }
        foreach ($codepoints as $cp) {
            if ($primary->glyphIndex($cp) === 0 && ! self::invisible($cp)) {
                $file = $this->catalog->fallback($pattern, $cp);
                if (is_null($file)) {
                    return $primary;
                }
                $face = $this->faces->face($file, $pixels);

                return $has($face) ? $face : $primary;
            }
        }

        return $primary;
    }

    /** Joiners and variation selectors: a face need not map them for the grapheme to be its. */
    private static function invisible(int $cp): bool
    {
        return $cp === 0x200D || $cp === 0x200C || ($cp >= 0xFE00 && $cp <= 0xFE0F) || ($cp >= 0xE0100 && $cp <= 0xE01EF);
    }

    /**
     * Grapheme clusters as byte ranges; each byte of invalid UTF-8 is a range of its own.
     *
     * @return list<array{int, int, bool}> offset, length, valid
     */
    private static function graphemes(string $utf8): array
    {
        $out = [];
        // Valid UTF-8 sequences in runs, or one stray byte.
        preg_match_all('/((?:[\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})+)|./s', $utf8, $pieces, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach ($pieces as $piece) {
            [$bytes, $offset] = $piece[0];
            if (($piece[1][0] ?? '') === '') {
                $out[] = [$offset, 1, false];

                continue;
            }
            $breaks = IntlBreakIterator::createCharacterInstance();
            $breaks->setText($bytes);
            $from = 0;
            foreach ($breaks as $boundary) {
                if ($boundary > $from) {
                    $out[] = [$offset + $from, $boundary - $from, true];
                    $from = $boundary;
                }
            }
        }

        return $out;
    }
}
