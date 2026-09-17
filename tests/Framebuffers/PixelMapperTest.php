<?php

use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\ChannelPalette;
use Surface\Contracts\Framebuffers\ChannelSpec;
use Surface\Contracts\Framebuffers\EInkColor;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\FramebufferException;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\NativeWindows\Views\Color;
use Surface\Framebuffers\PixelMapper;

function spec(PixelFormat $f, BitDepth $d, ?ChannelPalette $p = null): FormatSpec
{
    return new FormatSpec($f, $d, palette: $p);
}

it('mono thresholds on luma at 0.5', function () {
    $m = PixelMapper::for(spec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1));

    expect($m->map(Color::hex('#fff')))->toBe(1)
        ->and($m->map(Color::hex('#000')))->toBe(0)
        ->and($m->map(new Color(0.0, 0.7, 0.0)))->toBe(1)   // luma 0.5006
        ->and($m->map(new Color(1.0, 0.0, 0.0)))->toBe(0)   // luma 0.2126
        ->and($m->unmap(1)->red)->toBe(1.0)
        ->and($m->unmap(0)->red)->toBe(0.0);
});

it('quantises colour depths', function () {
    $c = new Color(1.0, 0.5, 0.0);

    expect(PixelMapper::for(spec(PixelFormat::ROW_MAJOR, BitDepth::B16))->map($c))->toBe(0xFC00)
        ->and(PixelMapper::for(spec(PixelFormat::ROW_MAJOR, BitDepth::B12))->map($c))->toBe(0xF80)
        ->and(PixelMapper::for(spec(PixelFormat::ROW_MAJOR, BitDepth::B18))->map($c))->toBe(0xFC8000)
        ->and(PixelMapper::for(spec(PixelFormat::ROW_MAJOR, BitDepth::B24))->map($c))->toBe(0xFF8000)
        ->and(PixelMapper::for(spec(PixelFormat::ROW_MAJOR, BitDepth::B32))->map(new Color(1.0, 0.5, 0.0, 0.5)))->toBe(0xFF800080)
        ->and(PixelMapper::for(spec(PixelFormat::ROW_MAJOR, BitDepth::B8))->map($c))->toBe(145);   // luma 0.5702 × 255
});

it('unmaps colour depths to the centre of the quantised step', function () {
    $m = PixelMapper::for(spec(PixelFormat::ROW_MAJOR, BitDepth::B16));
    $c = $m->unmap(0xF800);

    expect(round($c->red, 3))->toBe(1.0)->and($c->green)->toBe(0.0)->and($c->alpha)->toBe(1.0);
});

it('planar maps to a channel mask, white to paper', function () {
    $m = PixelMapper::for(spec(PixelFormat::PLANAR, BitDepth::B1, new ChannelPalette(
        new ChannelSpec(EInkColor::BLACK->value, true),
        new ChannelSpec(EInkColor::RED->value),
    )));

    expect($m->map(Color::hex('#000')))->toBe(1)
        ->and($m->map(Color::hex('#f00')))->toBe(2)
        ->and($m->map(Color::hex('#fff')))->toBe(0)
        ->and($m->map(Color::hex('#ffe0e0')))->toBe(0)
        ->and($m->unmap(2)->red)->toBe(1.0)
        ->and($m->unmap(0)->green)->toBe(1.0);
});

it('packed index maps to the wire code', function () {
    $m = PixelMapper::for(spec(PixelFormat::ROW_MAJOR, BitDepth::B2, new ChannelPalette(
        new ChannelSpec(EInkColor::BLACK->value, code: 0),
        new ChannelSpec(EInkColor::WHITE->value, code: 1),
        new ChannelSpec(EInkColor::YELLOW->value, code: 2),
        new ChannelSpec(EInkColor::RED->value, code: 3),
    )));

    expect($m->map(Color::hex('#f00')))->toBe(3)
        ->and($m->map(Color::hex('#fff')))->toBe(1)
        ->and($m->map(Color::hex('#ff0')))->toBe(2)
        ->and($m->unmap(2)->blue)->toBe(0.0)
        ->and($m->unmap(9)->red)->toBe(1.0);   // unknown code → white
});

it('refuses depths no packing has', function () {
    expect(fn () => PixelMapper::for(spec(PixelFormat::ROW_MAJOR, BitDepth::B10)))->toThrow(FramebufferException::class);
});
