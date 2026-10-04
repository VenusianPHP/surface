<?php

declare(strict_types=1);

use Surface\Drawing\DrawingManager;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Framebuffers\FramebufferManager;
use Surface\Images\ImagesManager;
use Surface\Rasterize\RasterizeManager;

/** A config repository over an array, for managers built without a container. */
function fakeConfig(array $items): object
{
    return new class($items) {
        public function __construct(private readonly array $items) {}

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->items[$key] ?? $default;
        }
    };
}

/** A framebuffer manager reading the given config, without a container. */
function framebuffers(array $config = []): FramebufferManager
{
    return new class($config) extends FramebufferManager {
        public function __construct(array $config)
        {
            $this->config = fakeConfig($config);
        }
    };
}

/** A rasterize manager reading the given config, without a container. */
function rasterize(array $config = []): RasterizeManager
{
    return new class($config) extends RasterizeManager {
        public function __construct(array $config)
        {
            $this->config = fakeConfig($config);
        }
    };
}

/** A drawing manager over those two, reading the given config. */
function drawing(array $config = []): DrawingManager
{
    return new DrawingManager(fakeConfig($config), framebuffers($config), rasterize($config));
}

/** An images manager reading the given config, minting on a framebuffer manager reading the same config. */
function images(array $config = []): ImagesManager
{
    return new class($config) extends ImagesManager {
        public function __construct(private readonly array $items)
        {
            $this->config = fakeConfig($items);
        }

        protected function framebuffers(): FramebufferDriver
        {
            return framebuffers($this->items)->driver($this->config->get('images.framebuffers'));
        }
    };
}
