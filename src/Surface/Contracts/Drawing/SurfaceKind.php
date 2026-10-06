<?php

namespace Surface\Contracts\Drawing;

/** The native surfaces a window can lend a GPU engine to present into. */
enum SurfaceKind: string
{
    /** A CAMetalLayer. */
    case METAL_LAYER = 'metal-layer';

    /** A VkSurfaceKHR made against the borrower's VkInstance. */
    case VULKAN_SURFACE = 'vulkan-surface';

    /** The window's own OpenGL context: the engine draws in it. */
    case GL_CONTEXT = 'gl-context';

    /** An SDL_Window wrapping the native view. */
    case SDL_WINDOW = 'sdl-window';

    /** A dmabuf texture builder the engine's exported image is shown through. */
    case DMABUF = 'dmabuf';

    /** The handle a surface of this kind always carries, by name. */
    public function handle(): string
    {
        return match ($this) {
            self::METAL_LAYER => 'layer',
            self::VULKAN_SURFACE => 'surface',
            self::GL_CONTEXT => 'context',
            self::SDL_WINDOW => 'window',
            self::DMABUF => 'texture_builder',
        };
    }

    /**
     * The engines that present into this kind.
     *
     * @return list<string>
     */
    public function engines(): array
    {
        return match ($this) {
            self::METAL_LAYER => ['metal', 'vulkan'],
            self::VULKAN_SURFACE, self::DMABUF => ['vulkan'],
            self::GL_CONTEXT => ['opengl'],
            self::SDL_WINDOW => ['sdl3'],
        };
    }
}
