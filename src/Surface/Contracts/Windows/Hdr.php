<?php

namespace Surface\Contracts\Windows;

/**
 * HDR state of a display or a window. sdrWhiteLevel is the brightness SDR
 * white maps to, as a multiple of the display's reference white; headroom is
 * how far above SDR white the display can go (1.0 is none).
 */
readonly class Hdr
{
    public function __construct(
        public bool $enabled,
        public float $sdrWhiteLevel = 1.0,
        public float $headroom = 1.0,
    ) {}
}
