<?php

namespace Surface\NativeWindows\Components;

use Surface\Contracts\NativeWindows\Views\Color;

/**
 * How loudly a Message speaks. Each severity carries its own fill and ink.
 */
enum MessageSeverity: string
{
    case INFO = 'info';
    case SUCCESS = 'success';
    case WARN = 'warn';
    case ERROR = 'error';

    public function fill(): Color
    {
        return match ($this) {
            self::INFO => Color::hex('#dbeafe'),
            self::SUCCESS => Color::hex('#dcfce7'),
            self::WARN => Color::hex('#fef9c3'),
            self::ERROR => Color::hex('#fee2e2'),
        };
    }

    public function ink(): Color
    {
        return match ($this) {
            self::INFO => Color::hex('#1d4ed8'),
            self::SUCCESS => Color::hex('#15803d'),
            self::WARN => Color::hex('#a16207'),
            self::ERROR => Color::hex('#b91c1c'),
        };
    }
}
