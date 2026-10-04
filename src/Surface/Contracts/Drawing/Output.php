<?php

namespace Surface\Contracts\Drawing;

use Surface\Contracts\Framebuffers\Framebuffer;

/**
 * Somewhere a framebuffer is shown: a toolkit canvas, an IC panel. Draw into
 * the bound framebuffer, directly or through a rendering engine, then
 * present() it. How a framebuffer gets bound is each output's own call.
 */
interface Output
{
    /** The framebuffer bound now; null before one is. */
    public function boundFramebuffer(): ?Framebuffer;

    /** Show what the bound framebuffer holds. */
    public function present(): static;
}
