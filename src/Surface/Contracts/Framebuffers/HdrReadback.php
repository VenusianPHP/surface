<?php

namespace Surface\Contracts\Framebuffers;

/**
 * A GPU target in an HDR colour space read back without clamping: what
 * HdrImage::fromRgba16f() takes, for a screenshot of an HDR frame.
 */
interface HdrReadback
{
    /** $region as half floats, little-endian, R G B A, linear extended sRGB, straight alpha. */
    public function readRgba16f(Region $region): string;
}
