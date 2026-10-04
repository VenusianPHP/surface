<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\BitOrder;
use Surface\Contracts\Framebuffers\ChannelOrder;
use Surface\Contracts\Framebuffers\ChannelPalette;
use Surface\Contracts\Framebuffers\ChannelSpec;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\FramebufferException;
use Surface\Contracts\Framebuffers\PageAxis;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Framebuffers\Layout;
use Venusian\Surface\Tests\Support\Framebuffers\Formats;

it('resolves a spec to its layout and the orders it leaves unsaid', function (): void {
    $pages = new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1);

    expect(Layout::of(Formats::mono()))->toBe(Layout::MONO_ROWS)
        ->and(Layout::of($pages))->toBe(Layout::MONO_PAGES)
        ->and(Layout::of(new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1, page_axis: PageAxis::HORIZONTAL)))->toBe(Layout::MONO_ROWS)
        ->and(Layout::of(Formats::spectra6()))->toBe(Layout::INDEX4)
        ->and(Layout::of(Formats::planarBwr()))->toBe(Layout::PLANAR)
        ->and(Layout::of(FormatSpec::bgra8()))->toBe(Layout::RGBA8888)
        ->and(Layout::bitOrder($pages))->toBe(BitOrder::LSB_FIRST)
        ->and(Layout::bitOrder(Formats::mono()))->toBe(BitOrder::MSB_FIRST)
        ->and(Layout::channelOrder(Formats::rgb565()))->toBe(ChannelOrder::RGB)
        ->and(Layout::channelOrder(new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B32)))->toBe(ChannelOrder::RGBA);
});

it('refuses what no layout stores, and sizes outside 1..65535', function (): void {
    expect(fn () => Layout::of(new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B10)))->toThrow(FramebufferException::class, 'not supported')
        ->and(fn () => Layout::of(new FormatSpec(PixelFormat::PLANAR, BitDepth::B1)))->toThrow(FramebufferException::class, 'PLANAR needs a palette')
        ->and(fn () => Layout::of(new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B24, channel_order: ChannelOrder::BGRA)))->toThrow(FramebufferException::class, 'BGRA is not a channel order')
        ->and(fn () => Layout::of(new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B2, palette: new ChannelPalette(new ChannelSpec(1, code: 4)))))->toThrow(FramebufferException::class, 'must fit 2 bits')
        ->and(fn () => Layout::size(0, 8))->toThrow(FramebufferException::class, 'sides are 1..65535')
        ->and(fn () => Layout::size(8, 65536))->toThrow(FramebufferException::class)
        ->and(Layout::size(65535, 1))->toBeNull();
});
