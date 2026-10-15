<?php

namespace Surface\Contracts\Windows\Styling;

use InvalidArgumentException;

/**
 * The visible rect of a scrolled view as fractions of its content: x and y are the rect's
 * top-left edge, width and height its size (1.0 when everything fits). Fractions are the one
 * unit every toolkit can give: Qt's plain text edit scrolls in lines, the others in points.
 */
final readonly class ScrollPosition
{
    /**
     * @throws InvalidArgumentException When a fraction is outside 0..1 or not finite.
     */
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
    ) {
        foreach ([$x, $y, $width, $height] as $fraction) {
            if (! is_finite($fraction) || $fraction < 0.0 || $fraction > 1.0) {
                throw new InvalidArgumentException("Scroll position fractions are 0..1, got {$x}, {$y}, {$width}, {$height}.");
            }
        }
    }

    /**
     * From a toolkit's offsets and sizes along each axis, in any one unit per axis.
     */
    public static function of(float $left, float $visibleWidth, float $contentWidth, float $top, float $visibleHeight, float $contentHeight): self
    {
        [$x, $width] = self::axis($left, $visibleWidth, $contentWidth);
        [$y, $height] = self::axis($top, $visibleHeight, $contentHeight);

        return new self($x, $y, $width, $height);
    }

    /**
     * The offset a toolkit scrolls to for a fraction of the content, kept so the visible
     * length stays inside it.
     */
    public static function offset(float $fraction, float $visible, float $content): float
    {
        return max(0.0, min($fraction * $content, $content - $visible));
    }

    /**
     * @return array{float, float} start and size fractions
     */
    private static function axis(float $offset, float $visible, float $content): array
    {
        if (! ($content > 0.0) || $visible >= $content) {
            return [0.0, 1.0];
        }
        $size = max(0.0, min(1.0, $visible / $content));

        return [max(0.0, min(1.0 - $size, $offset / $content)), $size];
    }
}
