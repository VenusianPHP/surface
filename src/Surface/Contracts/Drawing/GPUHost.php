<?php

namespace Surface\Contracts\Drawing;

/**
 * What a host hands an engine at attach: its native view or window handle as
 * pointer bits, the size in points, the backing scale, and at most one of the
 * things it lends — a GL surface, a CAMetalLayer it owns, a Vulkan surface lender.
 */
final readonly class GPUHost
{
    public function __construct(
        public int $native_view,
        public int $width,
        public int $height,
        public float $scale,
        public ?GLSurface $gl = null,
        public int $layer = 0,
        public ?VulkanSurfaceLender $vk = null,
    ) {}
}
