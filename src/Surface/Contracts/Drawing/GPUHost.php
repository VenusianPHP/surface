<?php

namespace Surface\Contracts\Drawing;

/**
 * What a window engine hands a GPU engine: raw pointer bits of the native
 * host node (0 when the engine has none), the host size in points, and the
 * backing scale. The GPU engine never boxes native_view — it is opaque here.
 */
final readonly class GPUHost
{
    public function __construct(
        public int $native_view,
        public int $width,
        public int $height,
        public float $scale,
        /** The GL surface the window engine minted for a GL_CONTEXT attach; null for a layer attach. */
        public ?GLSurface $gl = null,
    ) {}
}
