<?php

namespace Surface\Contracts\Framebuffers;

/** A ring of frames: writes go to the back frame, reads and flushes come from the front; present() flips. */
interface MultiFrameFramebuffer extends Framebuffer
{
    public function frames(): int;

    public function present(): void;

    /** The presented frame, for reading. */
    public function front(): Framebuffer;
}
