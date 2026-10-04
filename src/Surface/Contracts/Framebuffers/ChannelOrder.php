<?php

namespace Surface\Contracts\Framebuffers;

/**
 * The order a ROW_MAJOR colour pixel's channels are stored in. The pixel word
 * never changes with it: 0xRGB at 12 bits, RGB565 at 16, 0xRRGGBB at 18 and 24,
 * 0xRRGGBBAA at 32. RGB and BGR are the three-channel orders (12 to 24 bits);
 * the other four are the 32-bit orders.
 */
enum ChannelOrder: int
{
    case RGB = 0;
    case BGR = 1;
    case RGBA = 2;
    case BGRA = 3;
    case ARGB = 4;
    case ABGR = 5;

    public function hasAlpha(): bool
    {
        return $this->value >= self::RGBA->value;
    }
}
