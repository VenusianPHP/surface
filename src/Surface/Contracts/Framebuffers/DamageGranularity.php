<?php

namespace Surface\Contracts\Framebuffers;

/**
 * The smallest region a surface can usefully transmit.
 *
 * Published by every framebuffer so callers can snap damage to real transmit
 * units. Sub-unit precision is wasted work on page-addressed panels.
 *
 * Rect-based snap() returns when Fabricate Geometry Rect is available in 0.7.
 */
final readonly class DamageGranularity
{
    public function __construct(
        public int $unit_width,
        public int $unit_height,
        public int $surface_width,
        public int $surface_height,
    ) {}

    public static function pixel(int $surface_width, int $surface_height): self
    {
        return new self(1, 1, $surface_width, $surface_height);
    }

    public static function rows(int $rows, int $surface_width, int $surface_height): self
    {
        return new self($surface_width, $rows, $surface_width, $surface_height);
    }

    public static function wholeSurface(int $surface_width, int $surface_height): self
    {
        return new self($surface_width, $surface_height, $surface_width, $surface_height);
    }

    public function isPixelPerfect(): bool
    {
        return ($this->unit_width === 1) && ($this->unit_height === 1);
    }

    public function coversWholeSurface(): bool
    {
        return ($this->unit_width >= $this->surface_width)
            && ($this->unit_height >= $this->surface_height);
    }
}
