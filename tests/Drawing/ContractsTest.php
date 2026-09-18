<?php

use Surface\Contracts\Drawing\CPUDrawTarget;
use Surface\Contracts\Drawing\CPUEngine;
use Surface\Contracts\Drawing\CPUHost;
use Surface\Contracts\Drawing\GPUDrawTarget;
use Surface\Contracts\Drawing\GPUHost;
use Surface\Contracts\Drawing\SurfaceKind;
use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\NativeWindows\Views\OSGPUView;
use Surface\Contracts\Stage\GPUStagedWindow;
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

it('CPUEngine names the five in-house engines', function () {
    expect(array_map(fn (CPUEngine $e) => $e->value, CPUEngine::cases()))
        ->toBe(['dirty', 'full', 'epaper', 'paged', 'nframes']);
});

it('GPU targets are GPUDrawTargets; CPU targets are not', function () {
    expect(is_subclass_of(OSGPUView::class, GPUDrawTarget::class))->toBeTrue()
        ->and(is_subclass_of(GPUStagedWindow::class, GPUDrawTarget::class))->toBeTrue()
        ->and(is_subclass_of(CPUDrawTarget::class, GPUDrawTarget::class))->toBeFalse()
        ->and(method_exists(GPUDrawTarget::class, 'executor'))->toBeTrue()
        ->and(method_exists(CPUDrawTarget::class, 'flushRegion'))->toBeTrue();
});

it('a CPUHost defaults to two frames and eight page rows', function () {
    $host = new CPUHost(128, 64, new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1));

    expect($host->frames)->toBe(2)->and($host->page_rows)->toBe(8)->and($host->width)->toBe(128);
});
