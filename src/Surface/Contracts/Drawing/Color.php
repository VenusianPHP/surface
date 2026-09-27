<?php

namespace Surface\Contracts\Drawing;

/**
 * An sRGB colour, engine-neutral. Components are 0.0–1.0 floats — AppKit's
 * native unit; GTK CSS gets them re-expanded to rgba().
 *
 * Every surface that takes a colour takes this one: a GPU view, a CPU
 * canvas, a stage, a display panel, a native widget's tint. It lived under
 * NativeWindows until 0.8 because that is where the first caller was.
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
     *
     * @throws DrawingException On anything else.
     */
    public static function hex(string $hex): self
    {
        $hex = ltrim($hex, '#');

        if (preg_match('/^[0-9a-fA-F]{3}$/', $hex)) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/', $hex)) {
            throw DrawingException::notAHexColour($hex);
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
