<?php

namespace Surface\Contracts\Fonts;

/** How a face's glyph table and bytes are laid out. */
enum FontEncoding: string
{
    /** Adafruit GFX: 1bpp row-major, glyph i is code first + i. */
    case ADAFRUIT = 'adafruit';

    /** LVGL conversion: a reserved all-zero glyph 0, then the range; 1bpp or 4bpp. */
    case LVGL = 'lvgl';
}
