<?php

namespace Surface\Contracts\Drawing;

use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;

/**
 * Somewhere a framebuffer is shown: a toolkit canvas, an IC panel. Draw into
 * the bound framebuffer, directly or through a rendering engine, then
 * present() it. How a framebuffer gets bound is each output's own call.
 */
interface OutputTarget
{
    /** The framebuffer bound now; null before one is. */
    public function boundFramebuffer(): ?Framebuffer;

    /** This target's framebuffer, made with the target's defaults when none is bound. */
    public function framebuffer(): Framebuffer;

    /**
     * The target in device pixels. [0, 0] while it has no size yet (a view not laid out).
     *
     * @return array{int, int} width, height
     */
    public function pixelSize(): array;

    /** The format its pixels are shown in: RGBA8 for a window, the panel's wire format for a display. */
    public function pixelFormat(): FormatSpec;

    /** Show what the bound framebuffer holds. */
    public function present(): static;
}
