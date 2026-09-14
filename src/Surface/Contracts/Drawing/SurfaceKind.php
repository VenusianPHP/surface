<?php

namespace Surface\Contracts\Drawing;

/**
 * What a window or stage host must mint before an engine can attach. Read
 * from GPUEngineDriver::surfaceKind() before attach(); a host that cannot
 * mint a kind refuses by enum, never by package name.
 */
enum SurfaceKind: int
{
    /** The engine mints (or adopts a lent) CAMetalLayer; the host shows it. */
    case LAYER = 0;
    /** The host owns a GL context and lends it through GPUHost->gl. */
    case GL_CONTEXT = 1;
    /** The host turns the engine's VkInstance into a VkSurfaceKHR through GPUHost->vk. */
    case VULKAN_SURFACE = 2;
    /** The engine drives the host's own window handle (GPUHost->native_view) — SDL_GPU on an SDL window. */
    case HOST_WINDOW = 3;
}
