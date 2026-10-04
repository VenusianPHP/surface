<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\DamageTrackingFramebuffer;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\RingFramebuffer;
use Surface\Contracts\Windows\WindowException;
use Surface\Drawing\Velvet\VelvetGE;
use Surface\Framebuffers\Native\NativeFramebufferDriver;
use Surface\NutsAndBolts\Color;
use Surface\Rasterize\Native\NativeRasterizeDriver;
use Surface\Windows\Primitives\TKCanvas;
use Venusian\Surface\Tests\Fixtures\FakeCanvas;
use Venusian\Surface\Tests\Fixtures\FakeHost;

/*
 * The canvas, toolkit-neutral: FakeCanvas says the view measures 40 x 30 at
 * scale 2 and keeps every image present() hands the toolkit.
 */

function canvas(): FakeCanvas
{
    return (new FakeHost('main'))->column('m')->canvas('view');
}

afterEach(fn () => TKCanvas::resolveFramebuffersUsing(null));

it('measures itself in device pixels', function (): void {
    $canvas = canvas();

    expect($canvas->size())->toBe([40, 30])
        ->and($canvas->pixelSize())->toBe([80, 60]);

    $canvas->scale = 1.5;
    $canvas->measures = [41, 31];
    expect($canvas->pixelSize())->toBe([62, 47]);
});

it('hands out an RGBA8 framebuffer its own size in pixels, and the same one again', function (): void {
    $canvas = canvas();

    expect($canvas->boundFramebuffer())->toBeNull();
    $buffer = $canvas->framebuffer();

    expect([$buffer->viewportWidth(), $buffer->viewportHeight()])->toBe([80, 60])
        ->and($buffer->hostFormat())->toEqual(FormatSpec::rgba8())
        ->and($buffer)->not->toBeInstanceOf(DamageTrackingFramebuffer::class)
        ->and($canvas->framebuffer())->toBe($buffer)
        ->and($canvas->framebuffer('full'))->toBe($buffer)
        ->and($canvas->boundFramebuffer())->toBe($buffer);
});

it('makes a new framebuffer when the kind, the size or the frame count no longer matches', function (): void {
    $canvas = canvas();
    $full = $canvas->framebuffer();
    $dirty = $canvas->framebuffer('dirty');

    expect($dirty)->toBeInstanceOf(DamageTrackingFramebuffer::class)->not->toBe($full)
        ->and($canvas->framebuffer('dirty'))->toBe($dirty);

    $canvas->measures = [50, 30];                                   // the view was resized
    $resized = $canvas->framebuffer('dirty');
    expect($resized)->not->toBe($dirty)
        ->and($resized->viewportWidth())->toBe(100);

    $small = $canvas->framebuffer('dirty', 32, 24);                 // a chosen resolution, stretched over the view
    expect([$small->viewportWidth(), $small->viewportHeight()])->toBe([32, 24])
        ->and($canvas->framebuffer('dirty', 32, 24))->toBe($small);

    $ring = $canvas->framebuffer('ring', frames: 3);
    expect($ring)->toBeInstanceOf(RingFramebuffer::class)
        ->and($ring->frames())->toBe(3)
        ->and($canvas->framebuffer('ring', frames: 3))->toBe($ring)
        ->and($canvas->framebuffer('ring'))->not->toBe($ring)
        ->and($canvas->boundFramebuffer()->frames())->toBe(2);
});

it('refuses a kind a window cannot show, and a canvas with no size yet', function (): void {
    $canvas = canvas();
    $canvas->measures = [0, 0];

    expect(fn () => $canvas->framebuffer('paged'))->toThrow(WindowException::class, "A canvas framebuffer is 'full', 'dirty' or 'ring', got 'paged'.")
        ->and(fn () => $canvas->framebuffer())->toThrow(WindowException::class, "Canvas 'm.view' has no size yet: show its window first, or give framebuffer() a width and a height.")
        ->and($canvas->framebuffer('full', 16, 8)->viewportWidth())->toBe(16);
});

it('gets its framebuffer driver from the application, by name', function (): void {
    $asked = [];
    TKCanvas::resolveFramebuffersUsing(function (?string $driver) use (&$asked) {
        $asked[] = $driver;

        return new NativeFramebufferDriver();
    });
    $canvas = canvas();
    $canvas->framebuffer();
    $canvas->framebuffer('dirty', driver: 'native');

    expect($asked)->toBe([null, 'native']);
});

it('builds the named driver itself when no application answers', function (): void {
    expect(canvas()->framebuffer()->pointer())->toBe(0)
        ->and(canvas()->framebuffer(driver: 'native')->pointer())->toBe(0);
});

it('keeps its bytes in C when asked, with ext-fb loaded', function (): void {
    $canvas = canvas();
    $extended = $canvas->framebuffer(driver: 'extended');

    expect($extended->pointer())->not->toBe(0)
        ->and($canvas->framebuffer(driver: 'extended'))->toBe($extended)
        ->and($canvas->framebuffer())->toBe($extended)                    // no driver named: the bound one still fits
        ->and($canvas->framebuffer(driver: 'native'))->not->toBe($extended);
})->skip(! class_exists(FbBuffer::class), 'ext-fb 0.10 is not loaded in this PHP.');

it('shows a full framebuffer every time it is presented', function (): void {
    $canvas = canvas();

    expect(fn () => $canvas->present())->toThrow(WindowException::class, "Canvas 'm.view' has no framebuffer: call framebuffer() first.");

    $buffer = $canvas->framebuffer('full', 2, 1);
    $buffer->setPixel(1, 0, 0x11223344);
    $canvas->present()->present();

    expect($canvas->pixels)->toBe([["\0\0\0\0\x11\x22\x33\x44", 2, 1], ["\0\0\0\0\x11\x22\x33\x44", 2, 1]]);
});

it('shows a dirty framebuffer once, then only when something was drawn, starting its epoch anew', function (): void {
    $canvas = canvas();
    $buffer = $canvas->framebuffer('dirty', 2, 1);

    $canvas->present();                                              // never shown: it goes up, damage or not
    $canvas->present();                                              // nothing drawn since
    expect($canvas->pixels)->toHaveCount(1);

    $buffer->setPixel(0, 0, 0xFF0000FF);
    $canvas->present();
    expect($canvas->pixels)->toHaveCount(2)
        ->and($canvas->pixels[1])->toBe(["\xff\0\0\xff\0\0\0\0", 2, 1])
        ->and($buffer->damage())->toBe([]);

    $canvas->present();
    expect($canvas->pixels)->toHaveCount(2);
});

it('shows a ring\'s front frame, once per frame presented', function (): void {
    $canvas = canvas();
    $ring = $canvas->framebuffer('ring', 1, 1);

    $canvas->present()->present();                                   // the empty front, once
    expect($canvas->pixels)->toBe([["\0\0\0\0", 1, 1]]);

    $ring->setPixel(0, 0, 0xFF0000FF);                               // drawn into the back: not on screen yet
    $canvas->present();
    expect($canvas->pixels)->toHaveCount(1);

    $ring->present();
    $canvas->present()->present();
    expect($canvas->pixels)->toHaveCount(2)
        ->and($canvas->pixels[1])->toBe(["\xff\0\0\xff", 1, 1]);
});

it('shows what a rendering engine drew into its framebuffer', function (): void {
    $canvas = canvas();
    $velvet = new VelvetGE($canvas->framebuffer('ring', 4, 2), new NativeRasterizeDriver());

    $velvet->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 255))->fillRect(0, 0, 2, 2, Color::rgb(255, 0, 0)));
    $canvas->present();

    expect(bin2hex($canvas->pixels[0][0]))->toBe(str_repeat('ff0000ff'.'ff0000ff'.'0000ffff'.'0000ffff', 2))
        ->and([$canvas->pixels[0][1], $canvas->pixels[0][2]])->toBe([4, 2]);
});

it('starts over with a new framebuffer: it is shown even with nothing drawn', function (): void {
    $canvas = canvas();
    $canvas->framebuffer('dirty', 2, 1);
    $canvas->present();
    $canvas->framebuffer('dirty', 3, 1);
    $canvas->present();

    expect($canvas->pixels)->toHaveCount(2)
        ->and($canvas->pixels[1][1])->toBe(3);
});

it('refuses everything once removed', function (): void {
    $canvas = canvas();
    $canvas->framebuffer('full', 2, 2);
    $canvas->remove();

    expect(fn () => $canvas->present())->toThrow(WindowException::class, "was removed")
        ->and(fn () => $canvas->framebuffer())->toThrow(WindowException::class, 'was removed')
        ->and(fn () => $canvas->pixelSize())->toThrow(WindowException::class, 'was removed');
});
