<?php

namespace Surface\Contracts\Framebuffers;

/**
 * Optional on a Framebuffer: whole primitives handed down instead of spans
 * and point lists. The Rasterizer checks for it once at construction. No
 * driver implements it this slice; the seam is declared so ext-fb can grow
 * into it without a contract change.
 */
interface RastersNatively
{
    /** $region is already clipped to the surface. */
    public function fillRect(Region $region, int $value): void;

    /** Endpoints inclusive; the buffer clips to $clip. */
    public function line(int $x0, int $y0, int $x1, int $y1, int $value, Region $clip): void;

    /** 1:1 nearest blit of RGBA8 texels; texel alpha < 128 skipped; the buffer clips to $clip. */
    public function blitRgba8(string $rgba8, int $src_width, int $src_height, int $dx, int $dy, Region $clip): void;
}
