<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\BitOrder;
use Surface\Contracts\Framebuffers\ChannelOrder;
use Surface\Contracts\Framebuffers\ChannelPalette;
use Surface\Contracts\Framebuffers\ChannelSpec;
use Surface\Contracts\Framebuffers\DamageGranularity;
use Surface\Contracts\Framebuffers\EInkColor;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\Region;

it('names an ink\'s colour as channels and as a Color', function (): void {
    expect(EInkColor::WHITE->rgb())->toBe([255, 255, 255])
        ->and(EInkColor::ORANGE->rgb())->toBe([255, 128, 0])
        ->and(EInkColor::RED->color()->red)->toBe(1.0)
        ->and(EInkColor::RED->color()->green)->toBe(0.0)
        ->and(EInkColor::ORANGE->color()->green)->toBe(128 / 255);
});

it('defaults a channel code to its palette position', function (): void {
    $palette = new ChannelPalette(new ChannelSpec(EInkColor::BLACK->value), new ChannelSpec(EInkColor::RED->value, code: 7));

    expect($palette->codes())->toBe([0, 7])
        ->and($palette->indexOf(EInkColor::RED->value))->toBe(1)
        ->and($palette->indexOf(EInkColor::BLUE->value))->toBeNull();
});

it('compares a FormatSpec by every field, the palette by value', function (): void {
    $planar = fn (bool $inverted): FormatSpec => new FormatSpec(PixelFormat::PLANAR, BitDepth::B1, palette: new ChannelPalette(new ChannelSpec(1, $inverted)));
    $mono = new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1);

    expect($planar(true)->equals($planar(true)))->toBeTrue()
        ->and($planar(true)->equals($planar(false)))->toBeFalse()
        ->and($mono->equals(new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1, bit_order: BitOrder::LSB_FIRST)))->toBeFalse()
        ->and($mono->equals(new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1)))->toBeTrue()
        ->and(FormatSpec::rgba8()->equals(FormatSpec::bgra8()))->toBeFalse()
        ->and(FormatSpec::bgra8()->equals(FormatSpec::bgra8()))->toBeTrue();
});

it('names the two formats windows and graphics libraries exchange', function (): void {
    expect(FormatSpec::rgba8()->bit_depth)->toBe(BitDepth::B32)
        ->and(FormatSpec::rgba8()->channel_order)->toBe(ChannelOrder::RGBA)
        ->and(FormatSpec::bgra8()->channel_order)->toBe(ChannelOrder::BGRA)
        ->and(ChannelOrder::BGRA->hasAlpha())->toBeTrue()
        ->and(ChannelOrder::BGR->hasAlpha())->toBeFalse();
});

it('does rect math on a Region', function (): void {
    $a = new Region(1, 1, 4, 4);
    $b = new Region(3, 3, 4, 4);

    expect($a->intersect($b))->toEqual(new Region(3, 3, 2, 2))
        ->and($a->intersect(new Region(9, 9, 1, 1)))->toBeNull()
        ->and($a->union($b))->toEqual(new Region(1, 1, 6, 6))
        ->and($a->contains(4, 4))->toBeTrue()
        ->and($a->contains(5, 5))->toBeFalse()
        ->and($a->touches(new Region(5, 1, 1, 1)))->toBeTrue()
        ->and($a->touches(new Region(6, 1, 1, 1)))->toBeFalse()
        ->and((new Region(0, 0, 0, 3))->isEmpty())->toBeTrue();
});

it('snaps a Region outward to a granularity and clamps it to the surface', function (): void {
    $pages = DamageGranularity::rows(8, 8, 16);

    expect((new Region(3, 9, 1, 1))->snap($pages))->toEqual(new Region(0, 8, 8, 8))
        ->and((new Region(3, 7, 1, 2))->snap($pages))->toEqual(new Region(0, 0, 8, 16))
        ->and((new Region(2, 2, 3, 3))->snap(DamageGranularity::pixel(8, 8)))->toEqual(new Region(2, 2, 3, 3));
});
