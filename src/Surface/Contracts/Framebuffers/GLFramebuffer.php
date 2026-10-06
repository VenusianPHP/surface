<?php

namespace Surface\Contracts\Framebuffers;

/**
 * A GPU engine's own framebuffer: RGBA8, its pixels on the GPU. The engine
 * draws into it and says where; everything else on Framebuffer still works,
 * through readback and upload. A display that pipes reads a staging copy in
 * its own format, kept up to date region by region.
 */
interface GLFramebuffer extends DamageTrackingFramebuffer
{
    /**
     * The engine drew into these regions on the GPU: they join the damage
     * record, and pixels read back before now are no longer current.
     *
     * @param  list<Region>  $regions
     */
    public function drawn(array $regions): static;

    /**
     * Keep $staging as the copy a piping display reads: a framebuffer of this
     * size in the display's format, its bytes in C memory. pointer() answers
     * its address from now on. Null drops it.
     *
     * @throws FramebufferException When $staging is another size.
     */
    public function stageIn(?Framebuffer $staging): static;

    /**
     * Bring $region of the staging copy up to date with the GPU target, and
     * answer the staging copy.
     *
     * @throws FramebufferException When no staging copy is set.
     */
    public function stage(Region $region): Framebuffer;
}
