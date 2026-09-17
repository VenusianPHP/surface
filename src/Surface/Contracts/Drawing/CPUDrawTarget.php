<?php

namespace Surface\Contracts\Drawing;

use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Region;

/**
 * A DrawTarget rasterised on the CPU into a framebuffer the sketch never
 * touches. flush() answers the host format with zero transcode when $spec is
 * null; any other spec transcodes through RGBA8.
 */
interface CPUDrawTarget extends DrawTarget
{
    public function engine(): CPUEngine;

    public function hostFormat(): FormatSpec;

    /** @return string|list<int> */
    public function flush(?FormatSpec $spec = null, bool $as_array = false): string|array;

    /** @return string|list<int> */
    public function flushRegion(Region $region, ?FormatSpec $spec = null, bool $as_array = false): string|array;

    /** What this frame changed, snapped to the buffer's granularity; valid until the next renderFrame(). @return list<Region> */
    public function damage(): array;

    /** RGBA8 bytes, top-left first, drawable size. */
    public function rgba8(): string;
}
