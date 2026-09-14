<?php

namespace Surface\Contracts\Drawing;

/**
 * What a VULKAN_SURFACE host lends. The engine owns the instance and its
 * extension policy; the host alone knows its WSI, so it names the instance
 * extensions and turns the instance into a surface for its window.
 */
interface VulkanSurfaceLender
{
    /** @return list<string> Instance extensions the host's WSI needs, VK_KHR_surface included. */
    public function instanceExtensions(): array;

    /** A VkSurfaceKHR for the host window on this instance, as handle bits; 0 on failure. */
    public function createSurface(int $instance): int;

    public function destroySurface(int $instance, int $surface): void;

    /** @return array{int, int} The host window's size in pixels. */
    public function drawableSize(): array;
}
