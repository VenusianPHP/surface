<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\SurfaceKind;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Windows\WindowException;
use Venusian\Surface\Tests\Fixtures\FakeCanvas;
use Venusian\Surface\Tests\Fixtures\FakeHost;

/*
 * A canvas lends a native surface to a GPU engine: while it is lent, the
 * canvas shows what the borrower copies in, and its pixels are the borrower's.
 * FakeCanvas measures 40 x 30 at scale 2 and lends what $lends lists.
 */

function lendingCanvas(SurfaceKind ...$lends): FakeCanvas
{
    $canvas = (new FakeHost('main'))->column('m')->canvas('view');
    $canvas->lends = $lends;

    return $canvas;
}

function borrower(array $handles = []): FakeBorrower
{
    return new FakeBorrower(new FakeGLFramebuffer(80, 60), $handles);
}

it('lends nothing until its toolkit says what it lends', function () {
    $canvas = lendingCanvas();

    expect($canvas->surfaces())->toBe([])->and($canvas->lent())->toBeNull();
    expect(fn () => $canvas->lend(SurfaceKind::METAL_LAYER, borrower()))
        ->toThrow(WindowException::class, "Canvas 'm.view' lends no metal-layer surface (it lends: none).");
});

it('refuses a kind it does not lend, naming the ones it does', function () {
    lendingCanvas(SurfaceKind::GL_CONTEXT, SurfaceKind::DMABUF)->lend(SurfaceKind::METAL_LAYER, borrower());
})->throws(WindowException::class, "lends no metal-layer surface (it lends: gl-context, dmabuf).");

it('makes the surface against the borrower\'s handles and hands it out', function () {
    $canvas = lendingCanvas(SurfaceKind::VULKAN_SURFACE);

    $surface = $canvas->lend(SurfaceKind::VULKAN_SURFACE, borrower(['instance' => 0xABC]));

    expect($surface->kind)->toBe(SurfaceKind::VULKAN_SURFACE)
        ->and($surface->handle('surface'))->toBe(0xC0FFEE)
        ->and($surface->size())->toBe([80, 60])
        ->and($canvas->lent())->toBe($surface)
        ->and($canvas->log)->toContain('surface:vulkan-surface:{"instance":2748}');

    $canvas->measures = [50, 30];
    expect($surface->size())->toBe([100, 60]);
});

it('lends one surface at a time', function () {
    $canvas = lendingCanvas(SurfaceKind::GL_CONTEXT);
    $canvas->lend(SurfaceKind::GL_CONTEXT, borrower());

    $canvas->lend(SurfaceKind::GL_CONTEXT, borrower());
})->throws(WindowException::class, "Canvas 'm.view' has already lent its gl-context surface: reclaim() it first.");

it('answers the borrower\'s framebuffer while lent, and hands out none of its own', function () {
    $canvas = lendingCanvas(SurfaceKind::GL_CONTEXT);
    $to = borrower();
    $canvas->lend(SurfaceKind::GL_CONTEXT, $to);

    expect($canvas->boundFramebuffer())->toBe($to->target);
    expect(fn () => $canvas->framebuffer())
        ->toThrow(WindowException::class, "Canvas 'm.view' has lent its surface: its pixels are the borrower's. reclaim() it to draw into the canvas's own framebuffer.");
});

it('presents by asking the borrower to copy its frame in, and starts a fresh damage record', function () {
    $canvas = lendingCanvas(SurfaceKind::GL_CONTEXT);
    $to = borrower();
    $surface = $canvas->lend(SurfaceKind::GL_CONTEXT, $to);
    $to->target->drawn([new Region(0, 0, 80, 60)]);

    $canvas->present();

    expect($to->presented)->toBe([$surface])
        ->and($to->target->damage())->toBe([])
        ->and($canvas->pixels)->toBe([])
        ->and($canvas->addresses)->toBe([]);
});

it('copies nothing when the borrower has drawn nothing since the last copy', function () {
    $canvas = lendingCanvas(SurfaceKind::GL_CONTEXT);
    $to = borrower();
    $canvas->lend(SurfaceKind::GL_CONTEXT, $to);

    $canvas->present();
    $canvas->present();
    expect($to->presented)->toHaveCount(1);

    $to->target->drawn([new Region(4, 4, 8, 8)]);
    $canvas->present();
    expect($to->presented)->toHaveCount(2);
});

it('keeps the damage when no drawable was free, so the next present copies', function () {
    $canvas = lendingCanvas(SurfaceKind::GL_CONTEXT);
    $to = borrower();
    $canvas->lend(SurfaceKind::GL_CONTEXT, $to);
    $canvas->present();
    $to->target->drawn([new Region(4, 4, 8, 8)]);

    $to->free = false;
    $canvas->present();
    expect($to->target->damage())->toEqual([new Region(4, 4, 8, 8)]);

    $to->free = true;
    $canvas->present();
    expect($to->presented)->toHaveCount(3)
        ->and($to->target->damage())->toBe([]);
});

it('reclaims: the surface is removed and released, and the canvas shows its own framebuffer again', function () {
    $canvas = lendingCanvas(SurfaceKind::GL_CONTEXT);
    $own = $canvas->framebuffer('dirty', driver: 'native');
    $canvas->present();
    $to = borrower();
    $surface = $canvas->lend(SurfaceKind::GL_CONTEXT, $to);

    $canvas->reclaim();

    expect($surface->released())->toBeTrue()
        ->and($canvas->lent())->toBeNull()
        ->and($canvas->boundFramebuffer())->toBe($own)
        ->and($canvas->log)->toContain('unsurface:gl-context');

    // Its own framebuffer goes up whole: the window showed the borrower's frame in between.
    $canvas->present();
    expect($canvas->pixels)->toHaveCount(2)
        ->and($to->presented)->toBe([]);
});

it('releases the surface before it removes it: a borrower frees what it made while the native surface still exists', function () {
    $canvas = lendingCanvas(SurfaceKind::GL_CONTEXT);
    $surface = $canvas->lend(SurfaceKind::GL_CONTEXT, borrower());
    $surface->onRelease(function () use ($canvas): void {
        $canvas->log[] = 'released';
    });

    $canvas->reclaim();

    expect(array_search('released', $canvas->log, true))->toBeLessThan(array_search('unsurface:gl-context', $canvas->log, true));
});

it('reclaims nothing when nothing is lent', function () {
    $canvas = lendingCanvas(SurfaceKind::GL_CONTEXT);

    $canvas->reclaim();

    expect($canvas->log)->not->toContain('unsurface:gl-context');
});

it('reclaims before it is removed', function () {
    $canvas = lendingCanvas(SurfaceKind::METAL_LAYER);
    $surface = $canvas->lend(SurfaceKind::METAL_LAYER, borrower());

    $canvas->remove();

    expect($surface->released())->toBeTrue()
        ->and(array_search('unsurface:metal-layer', $canvas->log, true))->toBeLessThan(array_search('destroy', $canvas->log, true));
});
