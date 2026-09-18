<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Contracts\Fonts\GFXFont;

/**
 * A, B, C. Baseline-relative offsets (Adafruit): A and B rise 3, C rises 2, so
 * the ascent is 3. Row bits, MSB first, packed across rows:
 *   A 111/101/111 → 1111 0111 1… → 0xF7 0x80   at 0
 *   B 010/111/010 → 0101 1101 0… → 0x5D 0x00   at 2
 *   C 11/10/11    → 1110 11…     → 0xEC        at 4
 */
final class TinyFace extends GFXFont
{
    protected int $first = 0x41;

    protected int $last = 0x43;

    protected int $y_advance = 8;

    protected array $bitmaps = [0xF7, 0x80, 0x5D, 0x00, 0xEC];

    protected array $glyphs = [
        [0, 3, 3, 4, 0, -3],
        [2, 3, 3, 4, 0, -3],
        [4, 2, 3, 3, 0, -2],
    ];
}
