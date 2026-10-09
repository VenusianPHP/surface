<?php

namespace Surface\Contracts\Windows;

use Surface\Contracts\Framebuffers\Region;

/**
 * A display as its stager saw it when asked. Bounds are in points on the
 * desktop, which may start left of or above 0 on a multi-display desktop;
 * usable leaves out the menu bar, dock and panels.
 */
readonly class Display
{
    public function __construct(
        public int $id,
        public string $name,
        public Region $bounds,
        public Region $usable,
        public float $scale,
        public DisplayMode $current,
        public DisplayMode $desktop,
        public ?Hdr $hdr = null,
    ) {}
}
