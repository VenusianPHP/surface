<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\DrawingException;
use Surface\Contracts\Drawing\LentSurface;
use Surface\Contracts\Drawing\SurfaceKind;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Windows\Hdr;
use Surface\Contracts\Windows\ScaleFilter;
use Surface\Contracts\Windows\ScaleFit;

it('names the handle every surface of a kind carries', function () {
    expect(array_map(fn (SurfaceKind $kind): string => $kind->handle(), SurfaceKind::cases()))
        ->toBe(['layer', 'surface', 'context', 'window', 'display']);
});

it('names the engines that present into each kind', function () {
    expect(SurfaceKind::METAL_LAYER->engines())->toBe(['metal', 'vulkan'])
        ->and(SurfaceKind::VULKAN_SURFACE->engines())->toBe(['vulkan'])
        ->and(SurfaceKind::GL_CONTEXT->engines())->toBe(['opengl'])
        ->and(SurfaceKind::SDL_WINDOW->engines())->toBe(['sdl3'])
        ->and(SurfaceKind::DMABUF->engines())->toBe(['vulkan']);
});

it('carries its kind, its handles by name and its live size', function () {
    $size = [80, 60];
    $surface = new LentSurface(SurfaceKind::METAL_LAYER, ['layer' => 0xC0FFEE, 'view' => 7], function () use (&$size): array {
        return $size;
    });

    expect($surface->kind)->toBe(SurfaceKind::METAL_LAYER)
        ->and($surface->handle('layer'))->toBe(0xC0FFEE)
        ->and($surface->handles())->toBe(['layer' => 0xC0FFEE, 'view' => 7])
        ->and($surface->size())->toBe([80, 60]);

    $size = [100, 60];
    expect($surface->size())->toBe([100, 60]);
});

it('refuses to be built without the handle its kind carries', function () {
    new LentSurface(SurfaceKind::GL_CONTEXT, ['layer' => 1], fn (): array => [1, 1]);
})->throws(DrawingException::class, "A gl-context surface carries a 'context' handle.");

it('refuses a handle it does not have, naming the ones it has', function () {
    (new LentSurface(SurfaceKind::SDL_WINDOW, ['window' => 1], fn (): array => [1, 1]))->handle('layer');
})->throws(DrawingException::class, "This sdl-window surface has no 'layer' handle (it has: window).");

it('is released once, by whoever lent it', function () {
    $surface = new LentSurface(SurfaceKind::DMABUF, ['display' => 1], fn (): array => [1, 1]);

    expect($surface->released())->toBeFalse();
    $surface->release();
    expect($surface->released())->toBeTrue();
});

it('runs what was registered on release, once, in the order registered', function () {
    $surface = new LentSurface(SurfaceKind::GL_CONTEXT, ['context' => 1], fn (): array => [1, 1]);
    $ran = [];
    $surface->onRelease(function () use (&$ran, $surface): void {
        $ran[] = ['first', $surface->released()];
    });
    $surface->onRelease(function () use (&$ran): void {
        $ran[] = ['second'];
    });

    expect($ran)->toBe([]);
    $surface->release();
    $surface->release();

    expect($ran)->toBe([['first', true], ['second']]);
});

it('runs at once what is registered after it was released', function () {
    $surface = new LentSurface(SurfaceKind::GL_CONTEXT, ['context' => 1], fn (): array => [1, 1]);
    $surface->release();
    $ran = false;

    $surface->onRelease(function () use (&$ran): void {
        $ran = true;
    });

    expect($ran)->toBeTrue();
});

it('carries the window scaling and places a target by its fit, following changes until released', function (): void {
    $surface = new LentSurface(SurfaceKind::METAL_LAYER, ['layer' => 1], fn (): array => [640, 480]);
    $heard = [];
    $surface->onScaling(function (ScaleFilter $filter, ScaleFit $fit) use (&$heard): void { $heard[] = [$filter, $fit]; });

    $before = [$surface->scaling(), $surface->presentRect(320, 200)];
    $surface->changeScaling(ScaleFilter::Nearest, ScaleFit::Letterbox);
    $surface->changeScaling(ScaleFilter::Nearest, ScaleFit::Letterbox);
    $after = $surface->presentRect(320, 200);
    $surface->release();
    $surface->changeScaling(ScaleFilter::Linear, ScaleFit::Stretch);

    expect($before)->toEqual([[ScaleFilter::Linear, ScaleFit::Stretch], new Region(0, 0, 640, 480)])
        ->and($after)->toEqual(new Region(0, 40, 640, 400))
        ->and($heard)->toBe([[ScaleFilter::Nearest, ScaleFit::Letterbox]])
        ->and($surface->scaling())->toBe([ScaleFilter::Nearest, ScaleFit::Letterbox]);
});

it('reads the window HDR state live, null where the window reports none', function (): void {
    $now = null;
    $surface = new LentSurface(SurfaceKind::METAL_LAYER, ['layer' => 1], fn (): array => [1, 1], hdr: function () use (&$now): ?Hdr {
        return $now;
    });
    $plain = new LentSurface(SurfaceKind::METAL_LAYER, ['layer' => 1], fn (): array => [1, 1]);

    $off = $surface->hdr();
    $now = new Hdr(true, 2.0, 4.0);

    expect($off)->toBeNull()
        ->and($surface->hdr())->toBe($now)
        ->and($plain->hdr())->toBeNull();
});
