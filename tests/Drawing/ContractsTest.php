<?php

use Surface\Contracts\Drawing\GPUHost;
use Surface\Contracts\Drawing\SurfaceKind;
use Venusian\Surface\Tests\Support\Fakes\FakeVulkanLender;

it('SurfaceKind carries the four seam shapes in order', function () {
    expect(array_map(fn (SurfaceKind $kind) => [$kind->name, $kind->value], SurfaceKind::cases()))
        ->toBe([['LAYER', 0], ['GL_CONTEXT', 1], ['VULKAN_SURFACE', 2], ['HOST_WINDOW', 3]]);
});

it('a GPUHost lends nothing by default', function () {
    $host = new GPUHost(0, 10, 10, 1.0);

    expect($host->gl)->toBeNull()
        ->and($host->layer)->toBe(0)
        ->and($host->vk)->toBeNull();
});

it('a GPUHost can lend a layer it owns and a Vulkan surface lender', function () {
    $lender = new FakeVulkanLender();
    $host = new GPUHost(7, 320, 240, 2.0, layer: 4242, vk: $lender);

    expect($host->native_view)->toBe(7)
        ->and($host->layer)->toBe(4242)
        ->and($host->vk)->toBe($lender);
});

it('the fake lender answers the contract', function () {
    $lender = new FakeVulkanLender();

    expect($lender->instanceExtensions())->toBe(['VK_KHR_surface', 'VK_KHR_wayland_surface'])
        ->and($lender->createSurface(99))->toBe(1234)
        ->and($lender->created_on)->toBe([99]);
});
