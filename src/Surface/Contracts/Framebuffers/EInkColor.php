<?php

namespace Surface\Contracts\Framebuffers;

use Surface\Contracts\NativeWindows\Views\Color;

/** The logical ink colours a ChannelSpec names. WHITE is paper. */
enum EInkColor: int
{
    case WHITE = 0;
    case BLACK = 1;
    case RED = 2;
    case YELLOW = 3;
    case BLUE = 4;
    case GREEN = 5;
    case ORANGE = 6;

    public function color(): Color
    {
        return match ($this) {
            self::WHITE => new Color(1.0, 1.0, 1.0),
            self::BLACK => new Color(0.0, 0.0, 0.0),
            self::RED => new Color(1.0, 0.0, 0.0),
            self::YELLOW => new Color(1.0, 1.0, 0.0),
            self::BLUE => new Color(0.0, 0.0, 1.0),
            self::GREEN => new Color(0.0, 1.0, 0.0),
            self::ORANGE => new Color(1.0, 128 / 255, 0.0),
        };
    }
}
