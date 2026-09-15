<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Contracts\Drawing\VulkanSurfaceLender;

/** A Vulkan lender that records the instances it was asked about. */
final class FakeVulkanLender implements VulkanSurfaceLender
{
    /** @var list<int> */
    public array $created_on = [];

    /** @var list<array{int, int}> */
    public array $destroyed = [];

    /** @param list<string> $extensions */
    public function __construct(
        public array $extensions = ['VK_KHR_surface', 'VK_KHR_wayland_surface'],
        public int $surface = 1234,
        public array $size = [640, 480],
    ) {}

    public function instanceExtensions(): array
    {
        return $this->extensions;
    }

    public function createSurface(int $instance): int
    {
        $this->created_on[] = $instance;

        return $this->surface;
    }

    public function destroySurface(int $instance, int $surface): void
    {
        $this->destroyed[] = [$instance, $surface];
    }

    public function drawableSize(): array
    {
        return $this->size;
    }
}
