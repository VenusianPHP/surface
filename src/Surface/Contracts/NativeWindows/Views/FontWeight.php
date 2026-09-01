<?php

namespace Surface\Contracts\NativeWindows\Views;

/**
 * Engine-neutral font weight. AppKit maps to NSFontWeight doubles, GTK to
 * CSS weight numbers.
 */
enum FontWeight: string
{
    case LIGHT = 'light';
    case REGULAR = 'regular';
    case MEDIUM = 'medium';
    case SEMIBOLD = 'semibold';
    case BOLD = 'bold';
    case BLACK = 'black';

    /** The CSS font-weight number, GTK's unit. */
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
