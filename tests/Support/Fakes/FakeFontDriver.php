<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Contracts\Fonts\FontDriver;
use Surface\Contracts\Fonts\GlyphMask;
use Surface\Contracts\Fonts\ShapedGlyph;
use Surface\Contracts\Fonts\ShapedRun;

/**
 * A fixed TrueType driver needing no exts. Each byte is one glyph (glyph = ord, face 'fake#0'),
 * advancing pixelSize/2. A mask is a ceil(size/2) x ceil(size*0.75) box, 255 inside and 128 on its
 * outer ring; OBLIQUE zeroes its first column.
 */
final class FakeFontDriver implements FontDriver
{
    public int $shapes = 0;

    public int $masks = 0;

    public function driver(): string
    {
        return 'fake';
    }

    public function shape(string $pattern, float $pixelSize, string $utf8): ShapedRun
    {
        $this->shapes++;
        $glyphs = [];
        $step = $pixelSize / 2;
        for ($i = 0, $n = strlen($utf8); $i < $n; $i++) {
            $glyphs[] = new ShapedGlyph('fake#0', ord($utf8[$i]), $i * $step, 0.0, $step, $i, 0);
        }

        return new ShapedRun($utf8, $glyphs, strlen($utf8) * $step, $pixelSize * 0.75, $pixelSize * 0.25, $pixelSize);
    }

    public function mask(string $face, float $pixelSize, int $glyph, int $style): GlyphMask
    {
        $this->masks++;
        $w = (int) ceil($pixelSize / 2);
        $h = (int) ceil($pixelSize * 0.75);
        $bytes = '';
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = ($x === 0 || $y === 0 || $x === $w - 1 || $y === $h - 1) ? 128 : 255;
                $bytes .= chr(($style & ShapedGlyph::OBLIQUE) && $x === 0 ? 0 : $c);
            }
        }

        return new GlyphMask($bytes, $w, $h, 0, $h);
    }

    public function outline(string $face, float $pixelSize, int $glyph, int $style): array
    {
        $m = $this->mask($face, $pixelSize, $glyph, $style);
        $this->masks--;
        $t = (float) -$m->top;
        $l = (float) $m->left;

        return [[[$l, $t], [$l + $m->width, $t], [$l + $m->width, $t + $m->height], [$l, $t + $m->height]]];
    }

    public function close(): void {}
}
