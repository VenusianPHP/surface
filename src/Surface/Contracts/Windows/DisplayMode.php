<?php

namespace Surface\Contracts\Windows;

/** One mode a display can run at. Width and height are in points; pixels are those × pixelDensity. */
readonly class DisplayMode
{
    public function __construct(
        public int $displayId,
        public int $width,
        public int $height,
        public float $pixelDensity,
        public float $refreshRate,
    ) {}

    /** Same display, size, density and refresh rate. */
    public function equals(self $other): bool
    {
        return $this->displayId === $other->displayId && $this->width === $other->width && $this->height === $other->height
            && $this->pixelDensity === $other->pixelDensity && $this->refreshRate === $other->refreshRate;
    }
}
