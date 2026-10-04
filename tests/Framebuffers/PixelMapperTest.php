<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\FramebufferException;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Framebuffers\PixelMapper;
use Surface\Framebuffers\PixelMapperMode;
use Surface\NutsAndBolts\Color;
use Venusian\Surface\Tests\Support\Framebuffers\Formats;

function mapperAt(BitDepth $depth): PixelMapper
{
    return PixelMapper::for(new FormatSpec(PixelFormat::ROW_MAJOR, $depth));
}

it('picks a mode from the format', function (): void {
    expect(PixelMapper::for(Formats::mono())->mode())->toBe(PixelMapperMode::MONO)
        ->and(mapperAt(BitDepth::B4)->mode())->toBe(PixelMapperMode::GREY)
        ->and(PixelMapper::for(Formats::spectra6())->mode())->toBe(PixelMapperMode::INDEX)
        ->and(PixelMapper::for(Formats::planarBwr())->mode())->toBe(PixelMapperMode::PLANAR)
        ->and(mapperAt(BitDepth::B16)->mode())->toBe(PixelMapperMode::RGB)
        ->and(fn () => mapperAt(BitDepth::B10))->toThrow(FramebufferException::class)
        ->and(fn () => PixelMapper::for(new FormatSpec(PixelFormat::PLANAR, BitDepth::B1)))->toThrow(FramebufferException::class, 'PLANAR needs a palette');
});

it('thresholds mono at half luma and reads it back as black or white', function (): void {
    $mono = PixelMapper::for(Formats::mono());

    expect($mono->fromRgba8(128, 128, 128))->toBe(1)
        ->and($mono->fromRgba8(127, 127, 127))->toBe(0)
        ->and($mono->fromRgba8(0, 255, 0))->toBe(1)      // green carries most of the luma
        ->and($mono->fromRgba8(255, 0, 255))->toBe(0)
        ->and($mono->toRgba8(1))->toBe(0xFFFFFFFF)
        ->and($mono->toRgba8(0))->toBe(0x000000FF);
});

it('spreads grey over the levels a depth has', function (): void {
    expect(mapperAt(BitDepth::B8)->fromRgba8(255, 255, 255))->toBe(255)
        ->and(mapperAt(BitDepth::B4)->fromRgba8(85, 85, 85))->toBe(5)
        ->and(mapperAt(BitDepth::B2)->fromRgba8(170, 170, 170))->toBe(2)
        ->and(mapperAt(BitDepth::B4)->toRgba8(10))->toBe(0xAAAAAAFF)
        ->and(mapperAt(BitDepth::B2)->toRgba8(3))->toBe(0xFFFFFFFF);
});

it('packs RGB at each depth and widens it back', function (): void {
    expect(mapperAt(BitDepth::B12)->fromRgba8(255, 128, 0))->toBe(0xF80)
        ->and(mapperAt(BitDepth::B16)->fromRgba8(255, 255, 255))->toBe(0xFFFF)
        ->and(mapperAt(BitDepth::B16)->fromRgba8(255, 0, 0))->toBe(0xF800)
        ->and(mapperAt(BitDepth::B18)->fromRgba8(255, 255, 255))->toBe(0xFCFCFC)
        ->and(mapperAt(BitDepth::B24)->fromRgba8(1, 2, 3))->toBe(0x010203)
        ->and(mapperAt(BitDepth::B32)->fromRgba8(1, 2, 3, 4))->toBe(0x01020304)
        ->and(mapperAt(BitDepth::B16)->toRgba8(0xF800))->toBe(0xFF0000FF)
        ->and(mapperAt(BitDepth::B16)->toRgba8(0x8410))->toBe(0x848284FF)
        ->and(mapperAt(BitDepth::B18)->toRgba8(0xFCFCFC))->toBe(0xFFFFFFFF)
        ->and(mapperAt(BitDepth::B12)->toRgba8(0xF80))->toBe(0xFF8800FF)
        ->and(mapperAt(BitDepth::B32)->toRgba8(0x01020304))->toBe(0x01020304);
});

it('maps to the nearest palette entry and reads an unknown word as paper', function (): void {
    $spectra = PixelMapper::for(Formats::spectra6());
    $planar = PixelMapper::for(Formats::planarBwr());

    expect($spectra->fromRgba8(250, 10, 10))->toBe(3)        // red's code
        ->and($spectra->fromRgba8(240, 240, 20))->toBe(2)    // yellow's code
        ->and($spectra->toRgba8(5))->toBe(0x0000FFFF)
        ->and($spectra->toRgba8(4))->toBe(0xFFFFFFFF)        // no ink has code 4
        ->and($planar->fromRgba8(255, 255, 255))->toBe(0)    // paper
        ->and($planar->fromRgba8(0, 0, 0))->toBe(1)
        ->and($planar->fromRgba8(200, 30, 30))->toBe(2)
        ->and($planar->toRgba8(3))->toBe(0x000000FF)         // two inks: the lowest bit's
        ->and($planar->toRgba8(0))->toBe(0xFFFFFFFF);
});

it('quantises a Color to eight bits before mapping it, and answers one back', function (): void {
    $rgb = mapperAt(BitDepth::B32);

    expect($rgb->map(new Color(1.0, 0.5, 0.0, 0.25)))->toBe(0xFF800040)
        ->and($rgb->unmap(0xFF800040)->toHex())->toBe('#ff800040')
        ->and(PixelMapper::for(Formats::mono())->map(new Color(0.5, 0.5, 0.5)))->toBe(1)
        ->and(mapperAt(BitDepth::B16)->unmap(0xF800)->toHex())->toBe('#ff0000');
});
