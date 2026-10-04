<?php

namespace Surface\Contracts\Framebuffers;

use Surface\NutsAndBolts\Color;

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
        return Color::rgb(...$this->rgb());
    }

    /** @return array{int, int, int} 0..255 channels */
    public function rgb(): array
    {
        return match ($this) {
            self::WHITE => [255, 255, 255],
            self::BLACK => [0, 0, 0],
            self::RED => [255, 0, 0],
            self::YELLOW => [255, 255, 0],
            self::BLUE => [0, 0, 255],
            self::GREEN => [0, 255, 0],
            self::ORANGE => [255, 128, 0],
        };
    }
}
