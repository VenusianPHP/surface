<?php

namespace Surface\Contracts\Drawing;

/**
 * One present a device reports. frame is the device's count of presents
 * that landed, from 1; times are nanoseconds on hrtime(true)'s clock, null
 * where the device does not know them.
 */
readonly class PresentTiming
{
    public function __construct(
        public int $frame,
        public ?int $presentedAt = null,
        public ?int $refreshInterval = null,
    ) {}
}
