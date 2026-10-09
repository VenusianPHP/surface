<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\FramebufferException;
use Surface\Framebuffers\HdrImage;
use Surface\Framebuffers\Native\NativeFullFramebuffer;

/*
 * HdrImage: half floats in linear extended sRGB, shown in SDR to whatever
 * reads it as a framebuffer, uploaded as floats by a GPU device.
 */

it('keeps the half floats it was made from and shows them in SDR, sRGB-encoded and clamped', function (): void {
    $image = HdrImage::fromFloats([2.0, 0.5, 0.0, 1.0, -1.0, NAN, INF, 0.5], 2, 1);

    expect($image->rgba16f())->toBe(pack('v*', 0x4000, 0x3800, 0x0000, 0x3C00, 0xBC00, 0x7E00, 0x7C00, 0x3800))
        ->and(array_values(unpack('C*', $image->toRgba8())))->toBe([255, 188, 0, 255, 0, 0, 255, 128])
        ->and([$image->viewportWidth(), $image->viewportHeight()])->toBe([2, 1]);
});

it('reads half-float bytes back exactly, the largest half and the smallest subnormal included', function (): void {
    $bytes = pack('v*', 0x7BFF, 0x0001, 0x3C00, 0x3C00);
    $image = HdrImage::fromRgba16f($bytes, 1, 1);

    expect($image->rgba16f())->toBe($bytes)
        ->and(HdrImage::fromFloats([65504.0, 2 ** -24, 1.0, 1.0], 1, 1)->rgba16f())->toBe($bytes)
        ->and(array_values(unpack('C*', $image->toRgba8())))->toBe([255, 0, 255, 255]);
});

it('rounds a float past the largest half to infinity, and ties to even', function (): void {
    expect(HdrImage::fromFloats([65520.0, 1.0 + 2 ** -11, 1.0 + 3 * 2 ** -11, 0.0], 1, 1)->rgba16f())
        ->toBe(pack('v*', 0x7C00, 0x3C00, 0x3C02, 0x0000));
});

it('refuses pixels that do not fill its size', function (): void {
    expect(fn () => HdrImage::fromRgba16f(str_repeat("\0", 7), 1, 1))->toThrow(FramebufferException::class, 'An HdrImage of 1x1 is 8 bytes of RGBA16F, got 7.')
        ->and(fn () => HdrImage::fromFloats([1.0], 1, 1))->toThrow(FramebufferException::class, 'An HdrImage of 1x1 is 4 floats, got 1.')
        ->and(fn () => HdrImage::fromFloats([], 0, 1))->toThrow(FramebufferException::class, 'An HdrImage is at least 1x1, got 0x1.');
});

it('draws into other framebuffers as its SDR pixels and refuses every write', function (): void {
    $image = HdrImage::fromFloats([1.0, 0.0, 0.0, 1.0], 1, 1);
    $target = new NativeFullFramebuffer(FormatSpec::rgba8(), 2, 2);
    $image->blitTo($target, 1, 1);

    expect($target->getPixel(1, 1))->toBe($image->getPixel(0, 0))
        ->and(fn () => $image->setPixel(0, 0, 0))->toThrow(FramebufferException::class, 'An HdrImage is read-only: make a new one from new pixels.')
        ->and(fn () => $image->fill(0))->toThrow(FramebufferException::class, 'read-only')
        ->and(fn () => $image->writeRgba8("\0\0\0\0", 1, 1))->toThrow(FramebufferException::class, 'read-only');
});
