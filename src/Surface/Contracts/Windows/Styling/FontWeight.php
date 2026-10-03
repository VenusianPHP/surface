<?php

namespace Surface\Contracts\Windows\Styling;

/**
 * Named font weights; each driver maps them onto its toolkit's scale.
 * Windows only, never Drawing.
 */
enum FontWeight: string
{
    case LIGHT = 'light';
    case REGULAR = 'regular';
    case MEDIUM = 'medium';
    case SEMIBOLD = 'semibold';
    case BOLD = 'bold';
    case BLACK = 'black';

    /**
     * The CSS numeric weight (GTK CSS takes it as is).
     * @return int
     */
    public function toCssWeight(): int
    {
        return match ($this) {
            self::LIGHT => 300,
            self::REGULAR => 400,
            self::MEDIUM => 500,
            self::SEMIBOLD => 600,
            self::BOLD => 700,
            self::BLACK => 900,
        };
    }
}
