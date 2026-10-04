<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\ChannelOrder;
use Surface\Contracts\Framebuffers\ChannelPalette;
use Surface\Contracts\Framebuffers\ChannelSpec;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Framebuffers\FramebufferException;
use Surface\Contracts\Framebuffers\PixelFormat;
use Venusian\Surface\Tests\Support\Framebuffers\Formats;

it('refuses the same specs on every driver', function (FramebufferDriver $driver, FormatSpec $spec, string $why): void {
    expect(fn () => $driver->full($spec, 4, 4))->toThrow(FramebufferException::class, $why);
})->with('framebuffer drivers')->with([
    'ten bits' => [new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B10), 'not supported'],
    'one-bit row major' => [new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B1), 'not supported'],
    'mono at two bits' => [new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B2), 'not supported'],
    'planar without a palette' => [new FormatSpec(PixelFormat::PLANAR, BitDepth::B1), 'PLANAR needs a palette'],
    'an alpha order below 32 bits' => [new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B24, channel_order: ChannelOrder::BGRA), 'BGRA is not a channel order'],
    'a three-channel order at 32 bits' => [new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B32, channel_order: ChannelOrder::BGR), 'BGR is not a channel order'],
    'a channel order on mono' => [new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1, channel_order: ChannelOrder::RGB), 'RGB is not a channel order'],
    'a palette code past the depth' => [new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B2, palette: new ChannelPalette(new ChannelSpec(1, code: 4))), 'must fit 2 bits'],
    'seventeen inks' => [new FormatSpec(PixelFormat::PLANAR, BitDepth::B1, palette: new ChannelPalette(...array_fill(0, 17, new ChannelSpec(1)))), 'at most 16 inks'],
]);

it('refuses a size outside 1..65535 on every driver', function (FramebufferDriver $driver, int $width, int $height): void {
    expect(fn () => $driver->full(Formats::mono(), $width, $height))->toThrow(FramebufferException::class, 'sides are 1..65535')
        ->and(fn () => $driver->paged(Formats::mono(), $width, $height, 8))->toThrow(FramebufferException::class, 'sides are 1..65535');
})->with('framebuffer drivers')->with([[0, 8], [8, 0], [-1, 8], [65536, 8]]);
