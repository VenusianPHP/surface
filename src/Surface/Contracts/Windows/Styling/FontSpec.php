<?php

namespace Surface\Contracts\Windows\Styling;

use InvalidArgumentException;

/**
 * A font request for a native view: size in points, weight, and a family (null = the
 * toolkit's system font). Windows only, never Drawing.
 */
readonly class FontSpec
{
    /**
     * @param float $size points
     * @param FontWeight $weight
     * @param string|null $family null = the system font
     * @throws InvalidArgumentException When the size is not positive and finite.
     */
    public function __construct(
        public float $size,
        public FontWeight $weight = FontWeight::REGULAR,
        public ?string $family = null,
    ) {
        if (! is_finite($size) || $size <= 0.0) {
            throw new InvalidArgumentException("Font size must be positive and finite, got {$size}.");
        }
    }
}
