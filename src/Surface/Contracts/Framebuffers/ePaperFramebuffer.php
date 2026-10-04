<?php

namespace Surface\Contracts\Framebuffers;

/**
 * A whole ePaper frame in the controller's own RAM layout: single-ink mono,
 * one plane per ink, or packed palette codes (black/white up to Spectra 6).
 * It starts as paper and clear() returns it to paper.
 */
interface ePaperFramebuffer extends Framebuffer
{
    /** The word clear() fills with: the host's white. */
    public function paper(): int;

    /**
     * One ink as a one-bit plane, rows padded to a byte, x0 in bit 7. A planar
     * host answers the plane as stored (an inverted plane stays inverted); a
     * packed host answers 1 where the pixel is that ink; a mono host answers
     * its store for BLACK.
     *
     * @throws FramebufferException When the host has no such ink.
     */
    public function channelDump(EInkColor $ink): string;
}
