<?php

namespace Surface\Contracts\Drawing;

use Surface\Contracts\Framebuffers\Framebuffer;

/**
 * A target that takes a framebuffer's memory in C: present() hands it the
 * framebuffer's pointer() and damage, and no pixel byte passes through PHP.
 * canPipe() says whether a given framebuffer qualifies; what a target does
 * with one that does not is the target's own rule (a canvas takes a string,
 * a DirectEDisplay refuses it).
 */
interface Pipeable extends OutputTarget
{
    /** $framebuffer has a pointer() in the format this target takes. */
    public function canPipe(Framebuffer $framebuffer): bool;
}