<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\FramebufferException;
use Surface\Windows\WindowsServiceProvider;

/*
 * The canvas framebuffer format: config framebuffers.canvas_format when set, else the toolkit's binding
 * framebuffers.native_canvas_format, else RGBA8.
 */

it('takes the toolkit\'s native format when the config leaves it null', function (): void {
    expect(WindowsServiceProvider::canvasFormat(null, 'bgra8')->equals(FormatSpec::bgra8()))->toBeTrue();
});

it('lets a configured format override the toolkit\'s', function (): void {
    expect(WindowsServiceProvider::canvasFormat('rgba8', 'bgra8')->equals(FormatSpec::rgba8()))->toBeTrue();
});

it('is RGBA8 with neither', function (): void {
    expect(WindowsServiceProvider::canvasFormat(null, null)->equals(FormatSpec::rgba8()))->toBeTrue();
});

it('refuses a format name it does not know', function (): void {
    expect(fn () => WindowsServiceProvider::canvasFormat('argb32', null))->toThrow(FramebufferException::class, "'rgba8' or 'bgra8'")
        ->and(fn () => FormatSpec::named('rgb565'))->toThrow(FramebufferException::class);
});
