<?php

namespace Surface\Contracts\Windows\Styling;

use InvalidArgumentException;

/**
 * What the toolkit measured for a FontSpec, in points. Windows only, never Drawing.
 */
final readonly class FontMetrics
{
    /**
     * @param float $ascent above the baseline
     * @param float $descent below the baseline, positive
     * @param float $lineHeight baseline to baseline
     * @param float $averageCharWidth
     * @param bool $fixedPitch every character the same advance
     * @throws InvalidArgumentException When a size is negative or not finite.
     */
    public function __construct(
        public float $ascent,
        public float $descent,
        public float $lineHeight,
        public float $averageCharWidth,
        public bool $fixedPitch,
    ) {
        foreach (['ascent' => $ascent, 'descent' => $descent, 'line height' => $lineHeight, 'average char width' => $averageCharWidth] as $name => $value) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException("A font's {$name} must be finite, got {$value}.");
            }
            if ($value < 0.0) {
                throw new InvalidArgumentException("A font's {$name} is not negative, got {$value}.");
            }
        }
    }
}
