<?php

use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\ChannelPalette;
use Surface\Contracts\Framebuffers\ChannelSpec;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\FramebufferException;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\Region;
use Surface\Framebuffers\Packings\MonoHorizontalPacking;
use Surface\Framebuffers\Packings\Packing;
use Surface\Framebuffers\Packings\PlanarPacking;
use Surface\Framebuffers\Php\FullFramebuffer;

it('picks the packing by format and depth', function () {
    expect(Packing::for(new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1), 8, 1))->toBeInstanceOf(MonoHorizontalPacking::class)
        ->and(Packing::for(new FormatSpec(PixelFormat::PLANAR, BitDepth::B1, palette: new ChannelPalette(new ChannelSpec(1))), 8, 1))->toBeInstanceOf(PlanarPacking::class)
        ->and(fn () => Packing::for(new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B10), 1, 1))->toThrow(FramebufferException::class)
        ->and(fn () => Packing::for(new FormatSpec(PixelFormat::PLANAR, BitDepth::B1), 1, 1))->toThrow(FramebufferException::class);
});

it('a write outside the surface throws', function () {
    $fb = new FullFramebuffer(new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B16), 2, 2);

    expect(fn () => $fb->setPixel(2, 0, 1))->toThrow(FramebufferException::class)
        ->and(fn () => $fb->getPixel(0, -1))->toThrow(FramebufferException::class);
});

it('setSegment clips to the surface and fill touches only real pixels', function () {
    $fb = new FullFramebuffer(new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1), 10, 2);
    $fb->setSegment(8, 1, 10, 5, 1);
    expect(bin2hex($fb->bytes()))->toBe('000000c0');   // row 1 is bytes 2,3: x8, x9 → byte 3 bits 7,6 (MSB)
    $fb->fill(1);
    expect(bin2hex($fb->bytes()))->toBe('ffc0ffc0');
});

it('blitFrom goes through RGBA and clips', function () {
    $src = new FullFramebuffer(new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B24), 2, 1);
    $src->setPixel(0, 0, 0xFFFFFF)->setPixel(1, 0, 0x000000);
    $dst = new FullFramebuffer(new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1), 8, 1);
    $dst->blitFrom($src, 7, 0);

    expect(bin2hex($dst->bytes()))->toBe('01');   // white lands at x7; black at x8 is dropped
});

it('flush to the host format with as_array answers ints', function () {
    $fb = new FullFramebuffer(new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B16), 1, 1);
    $fb->setPixel(0, 0, 0x1234);

    expect($fb->flush($fb->hostFormat(), true))->toBe([0x12, 0x34]);
});

it('dump answers one plane of a planar buffer', function () {
    $fb = new FullFramebuffer(new FormatSpec(PixelFormat::PLANAR, BitDepth::B1, palette: new ChannelPalette(new ChannelSpec(1, true), new ChannelSpec(2))), 8, 1);
    $fb->setPixel(0, 0, 2);

    expect(bin2hex($fb->dump(0)))->toBe('ff')->and(bin2hex($fb->dump(1)))->toBe('80')->and(bin2hex($fb->dump()))->toBe('ff80');
});

it('a region that is not page aligned on a vertical-page host still answers whole pages', function () {
    $fb = new FullFramebuffer(new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1), 2, 16);
    $fb->setPixel(1, 8, 1);

    expect(bin2hex($fb->flushRegion(new Region(0, 8, 2, 8), $fb->hostFormat())))->toBe('0001');
});
