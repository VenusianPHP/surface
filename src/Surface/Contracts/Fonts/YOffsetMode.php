<?php

namespace Surface\Contracts\Fonts;

/** What a glyph's y_offset is measured from. */
enum YOffsetMode: string
{
    /** From the baseline (Adafruit) — or already usable as a top offset (LVGL Montserrat). */
    case RAW = 'raw';

    /** From the line bottom (LVGL Unscii): top = lineHeight - height - y_offset. */
    case LINE = 'line';
}
