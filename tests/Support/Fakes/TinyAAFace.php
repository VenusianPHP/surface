<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Contracts\Fonts\GFXFont;

/** LVGL 4bpp: reserved glyph 0, then A = nibbles F 3 / 9 0 → bytes 0xF3 0x90. Offsets ≥ 0 → LINE mode. */
final class TinyAAFace extends GFXFont
{
    protected int $first = 0x41;

    protected int $last = 0x41;

    protected int $y_advance = 8;

    protected int $bits_per_pixel = 4;

    protected array $bitmaps = [0xF3, 0x90];

    protected array $glyphs = [
        [0, 0, 0, 0, 0, 0],
        [0, 2, 2, 3, 0, 0],
    ];
}
