<?php

use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\BitOrder;
use Surface\Contracts\Framebuffers\ChannelPalette;
use Surface\Contracts\Framebuffers\ChannelSpec;
use Surface\Contracts\Framebuffers\DamageGranularity;
use Surface\Contracts\Framebuffers\EInkColor;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\Region;

it('BitDepth carries B2 and B4', function () {
    expect(BitDepth::B2->value)->toBe(2)->and(BitDepth::B4->value)->toBe(4);
});

it('EInkColor maps to sRGB', function () {
    expect(EInkColor::WHITE->color()->red)->toBe(1.0)
        ->and(EInkColor::BLACK->color()->blue)->toBe(0.0)
        ->and(EInkColor::RED->color()->red)->toBe(1.0)
        ->and(EInkColor::RED->color()->green)->toBe(0.0)
        ->and(EInkColor::ORANGE->color()->green)->toBe(128 / 255);
});

it('a channel code defaults to its palette position', function () {
    $palette = new ChannelPalette(new ChannelSpec(EInkColor::BLACK->value), new ChannelSpec(EInkColor::RED->value, code: 7));

    expect($palette->codes())->toBe([0, 7])
        ->and($palette->indexOf(EInkColor::RED->value))->toBe(1)
        ->and($palette->indexOf(EInkColor::BLUE->value))->toBeNull();
});

it('FormatSpec equality compares every field and the palette by value', function () {
    $a = new FormatSpec(PixelFormat::PLANAR, BitDepth::B1, palette: new ChannelPalette(new ChannelSpec(1, true)));
    $b = new FormatSpec(PixelFormat::PLANAR, BitDepth::B1, palette: new ChannelPalette(new ChannelSpec(1, true)));
    $c = new FormatSpec(PixelFormat::PLANAR, BitDepth::B1, palette: new ChannelPalette(new ChannelSpec(1, false)));
    $d = new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1, bit_order: BitOrder::LSB_FIRST);
    $e = new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1);

    expect($a->equals($b))->toBeTrue()
        ->and($a->equals($c))->toBeFalse()
        ->and($d->equals($e))->toBeFalse()
        ->and($e->equals(new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1)))->toBeTrue();
});

it('Region does rect math', function () {
    $a = new Region(1, 1, 4, 4);
    $b = new Region(3, 3, 4, 4);
    $far = new Region(9, 9, 1, 1);

    expect($a->intersect($b))->toEqual(new Region(3, 3, 2, 2))
        ->and($a->intersect($far))->toBeNull()
        ->and($a->union($b))->toEqual(new Region(1, 1, 6, 6))
        ->and($a->contains(4, 4))->toBeTrue()
        ->and($a->contains(5, 5))->toBeFalse()
        ->and($a->touches($b))->toBeTrue()
        ->and($a->touches(new Region(5, 1, 1, 1)))->toBeTrue()
        ->and($a->touches(new Region(6, 1, 1, 1)))->toBeFalse()
        ->and(Region::wholeSurface(8, 16))->toEqual(new Region(0, 0, 8, 16));
});

it('Region snaps outward to a granularity and clamps to the surface', function () {
    $g = DamageGranularity::rows(8, 8, 16);

    expect((new Region(3, 9, 1, 1))->snap($g))->toEqual(new Region(0, 8, 8, 8))
        ->and((new Region(3, 7, 1, 2))->snap($g))->toEqual(new Region(0, 0, 8, 16))
        ->and((new Region(2, 2, 3, 3))->snap(DamageGranularity::pixel(8, 8)))->toEqual(new Region(2, 2, 3, 3));
});
