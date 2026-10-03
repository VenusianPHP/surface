<?php

namespace Surface\NutsAndBolts;

use InvalidArgumentException;

/**
 * A colour as four 0..1 components. Shared by every Surface component that paints:
 * windows style native views with it, drawing fills with it.
 */
readonly class Color
{
    /**
     * @param float $red 0..1
     * @param float $green 0..1
     * @param float $blue 0..1
     * @param float $alpha 0..1, 1 = opaque
     * @throws InvalidArgumentException When a component is not a finite 0..1.
     */
    public function __construct(
        public float $red,
        public float $green,
        public float $blue,
        public float $alpha = 1.0,
    ) {
        foreach ([$red, $green, $blue, $alpha] as $component) {
            if (! is_finite($component) || $component < 0.0 || $component > 1.0) {
                throw new InvalidArgumentException("Color components are 0..1, got {$component}.");
            }
        }
    }

    /**
     * Opaque colour from 0..255 channels.
     *
     * @param int $red
     * @param int $green
     * @param int $blue
     * @return self
     */
    public static function rgb(int $red, int $green, int $blue): self
    {
        return self::rgba($red, $green, $blue, 1.0);
    }

    /**
     * Colour from 0..255 channels and a 0..1 alpha.
     *
     * @param int $red
     * @param int $green
     * @param int $blue
     * @param float $alpha
     * @return self
     */
    public static function rgba(int $red, int $green, int $blue, float $alpha): self
    {
        return new self($red / 255, $green / 255, $blue / 255, $alpha);
    }

    /**
     * Colour from `#rgb`, `#rrggbb` or `#rrggbbaa` (the `#` is optional).
     *
     * @param string $hex
     * @return self
     * @throws InvalidArgumentException When the string is none of the three forms.
     */
    public static function hex(string $hex): self
    {
        $digits = ltrim($hex, '#');
        if (! preg_match('/^[0-9a-f]{3}$|^[0-9a-f]{6}$|^[0-9a-f]{8}$/iD', $digits)) {
            throw new InvalidArgumentException("'{$hex}' is not #rgb, #rrggbb or #rrggbbaa.");
        }
        if (strlen($digits) === 3) {
            $digits = $digits[0].$digits[0].$digits[1].$digits[1].$digits[2].$digits[2];
        }
        $parts = array_map(fn (string $pair): int => (int) hexdec($pair), str_split($digits, 2));

        return self::rgba($parts[0], $parts[1], $parts[2], isset($parts[3]) ? $parts[3] / 255 : 1.0);
    }

    /**
     * `rgba(r, g, b, a)` with 0..255 channels, as CSS and Qt style sheets take it.
     *
     * @return string
     */
    public function toCss(): string
    {
        $alpha = rtrim(rtrim(number_format($this->alpha, 3, '.', ''), '0'), '.');

        return sprintf('rgba(%d, %d, %d, %s)', round($this->red * 255), round($this->green * 255), round($this->blue * 255), $alpha);
    }

    /**
     * `#rrggbb`, or `#rrggbbaa` when the colour is not opaque.
     *
     * @return string
     */
    public function toHex(): string
    {
        $hex = sprintf('#%02x%02x%02x', round($this->red * 255), round($this->green * 255), round($this->blue * 255));

        return $this->alpha < 1.0 ? $hex.sprintf('%02x', round($this->alpha * 255)) : $hex;
    }
}
