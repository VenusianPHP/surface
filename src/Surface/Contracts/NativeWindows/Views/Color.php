<?php

namespace Surface\Contracts\NativeWindows\Views;

use Surface\Contracts\NativeWindows\WindowableException;

/**
 * An sRGB colour, engine-neutral. Components are 0.0–1.0 floats — AppKit's
 * native unit; GTK CSS gets them re-expanded to rgba().
 */
final class Color
{
    public function __construct(
        public readonly float $red,
        public readonly float $green,
        public readonly float $blue,
        public readonly float $alpha = 1.0,
    ) {}

    /**
     * From '#rgb', '#rrggbb' or '#rrggbbaa', hash optional.
     * @throws WindowableException On anything else.
     */
    public static function hex(string $hex): self
    {
        $hex = ltrim($hex, '#');

        if (preg_match('/^[0-9a-fA-F]{3}$/', $hex)) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/', $hex)) {
            throw new WindowableException("'{$hex}' is not a hex colour.");
        }

        return new self(
            red: hexdec(substr($hex, 0, 2)) / 255.0,
            green: hexdec(substr($hex, 2, 2)) / 255.0,
            blue: hexdec(substr($hex, 4, 2)) / 255.0,
            alpha: strlen($hex) === 8 ? hexdec(substr($hex, 6, 2)) / 255.0 : 1.0,
        );
    }

    /** As a CSS rgba() term, for the GTK engine. */
    public function toCss(): string
    {
        return sprintf(
            'rgba(%d, %d, %d, %s)',
            (int) round($this->red * 255),
            (int) round($this->green * 255),
            (int) round($this->blue * 255),
            rtrim(rtrim(number_format($this->alpha, 3, '.', ''), '0'), '.') ?: '0',
        );
    }
}
