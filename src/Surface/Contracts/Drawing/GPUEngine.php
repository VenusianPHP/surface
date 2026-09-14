<?php

namespace Surface\Contracts\Drawing;

/** The GPU engines a sketch may name. Resolution is by container alias, never by class. */
enum GPUEngine: string
{
    case METAL = 'metal';
    case OPENGL = 'opengl';
    case VULKAN = 'vulkan';
    case SDL3 = 'sdl3';
}
