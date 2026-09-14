<?php

namespace Surface\Contracts\Drawing;

/**
 * What a window engine must mint before it calls attach(): a host node
 * whose layer the engine hands back (Metal, Vulkan-on-Mac), or a GL
 * surface whose context the host owns and lends (OpenGL). A kind the
 * window engine cannot mint is GPUViewException::unsupported(), decided
 * by this enum — no package is named.
 */
enum SurfaceKind: int
{
    case LAYER = 0;
    case GL_CONTEXT = 1;
}
