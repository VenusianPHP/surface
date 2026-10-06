<?php

namespace Surface\Contracts\Drawing;

use Surface\Contracts\Framebuffers\Framebuffer;

/** Who a window lends a surface to: a GPU engine. */
interface SurfaceBorrower
{
    /** What the window answers from boundFramebuffer() while its surface is lent. */
    public function framebuffer(): Framebuffer;

    /**
     * Native handles the window needs to make the surface: 'instance' (a
     * VkInstance) for a VULKAN_SURFACE; [] where the window needs none.
     *
     * @return array<string, int>
     */
    public function lendingHandles(): array;

    /** Copy the current frame into the lent surface on the GPU. False when no drawable was free and nothing was copied. */
    public function presentInto(LentSurface $surface): bool;
}
