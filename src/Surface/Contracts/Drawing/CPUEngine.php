<?php

namespace Surface\Contracts\Drawing;

/** The GPU engines a sketch may name. Resolution is by container alias, never by class. */
enum CPUEngine: string
{
    case PHPDAFRUIT = 'phpdafruit';
    case U8G2 = 'u8g2';
    case LVGL = 'lvgl';
}
