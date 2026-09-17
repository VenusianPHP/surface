<?php

use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\ChannelPalette;
use Surface\Contracts\Framebuffers\ChannelSpec;
use Surface\Contracts\Framebuffers\EInkColor;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\FramebufferException;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\Region;
use Surface\Framebuffers\Php\PhpFramebufferDriver;

function drv(): PhpFramebufferDriver
{
    return new PhpFramebufferDriver();
}

function mono(): FormatSpec
{
    return new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1);
}

it('names itself php and mints the five kinds', function () {
    $d = drv();

    expect($d->driver())->toBe('php')
        ->and($d->full(mono(), 8, 8)->preservesContentsOnPresent())->toBeTrue()
        ->and($d->dirty(mono(), 8, 8)->damage())->toBe([])
        ->and($d->paged(mono(), 8, 16, 8)->pages())->toBe(2)
        ->and($d->ring(mono(), 8, 8, 3)->frames())->toBe(3);
});

it('dirty records a segment as one region and setPixels as merged pixels', function () {
    $fb = drv()->dirty(new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B8), 8, 8);
    $fb->beginEpoch();
    $fb->setSegment(1, 1, 3, 2, 9);
    $fb->setPixels([[5, 5, 1], [6, 5, 1]]);

    expect(array_map(fn (Region $r) => [$r->x, $r->y, $r->width, $r->height], $fb->damage()))->toBe([[1, 1, 3, 2], [5, 5, 2, 1]]);
});

it('epaper accepts planar, single-ink mono and packed index, refuses colour', function () {
    $d = drv();
    $planar = new FormatSpec(PixelFormat::PLANAR, BitDepth::B1, palette: new ChannelPalette(new ChannelSpec(EInkColor::BLACK->value, true), new ChannelSpec(EInkColor::RED->value)));
    $packed = new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B2, palette: new ChannelPalette(new ChannelSpec(EInkColor::BLACK->value), new ChannelSpec(EInkColor::WHITE->value)));

    expect($d->epaper($planar, 8, 1))->not->toBeNull()
        ->and($d->epaper(mono(), 8, 1))->not->toBeNull()
        ->and($d->epaper($packed, 8, 1))->not->toBeNull()
        ->and(fn () => $d->epaper(new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B16), 8, 1))->toThrow(FramebufferException::class)
        ->and(fn () => $d->epaper(new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B2), 8, 1))->toThrow(FramebufferException::class);
});

it('epaper channelDump answers one ink as a mono plane on either host', function () {
    $d = drv();
    $planar = $d->epaper(new FormatSpec(PixelFormat::PLANAR, BitDepth::B1, palette: new ChannelPalette(new ChannelSpec(EInkColor::BLACK->value, true), new ChannelSpec(EInkColor::RED->value))), 8, 1);
    $planar->setPixel(7, 0, 2);
    $packed = $d->epaper(new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B2, palette: new ChannelPalette(new ChannelSpec(EInkColor::BLACK->value, code: 0), new ChannelSpec(EInkColor::WHITE->value, code: 1), new ChannelSpec(EInkColor::RED->value, code: 3))), 8, 1);
    $packed->setPixel(0, 0, 3);

    expect(bin2hex($planar->channelDump(EInkColor::RED)))->toBe('01')
        ->and(bin2hex($planar->channelDump(EInkColor::BLACK)))->toBe('ff')   // the inverted plane as stored
        ->and(bin2hex($packed->channelDump(EInkColor::RED)))->toBe('80');
});

it('paged refuses page rows that split an SSD1306 page and non-positive rows', function () {
    $vp = new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1);

    expect(fn () => drv()->paged($vp, 8, 16, 4))->toThrow(FramebufferException::class)
        ->and(fn () => drv()->paged(mono(), 8, 16, 0))->toThrow(FramebufferException::class)
        ->and(drv()->paged($vp, 8, 16, 16)->pages())->toBe(1);
});

it('paged reads outside the page answer 0 and flushRegion clips to the page', function () {
    $fb = drv()->paged(mono(), 8, 16, 8);
    $fb->setPage(1);
    $fb->setSegment(0, 8, 8, 8, 1);

    expect($fb->getPixel(0, 0))->toBe(0)
        ->and($fb->getPixel(0, 8))->toBe(1)
        ->and(bin2hex($fb->flushRegion(new Region(0, 0, 8, 16), mono())))->toBe('ffffffffffffffff')
        ->and(strlen($fb->toRgba8()))->toBe(8 * 8 * 4);
});

it('ring needs at least two frames and reads the front', function () {
    expect(fn () => drv()->ring(mono(), 8, 8, 1))->toThrow(FramebufferException::class);
    $fb = drv()->ring(mono(), 8, 1, 2);
    $fb->fill(1);

    expect(bin2hex($fb->front()->flush(mono())))->toBe('00')->and(bin2hex($fb->toRgba8()))->toBe(str_repeat('000000ff', 8));
    $fb->present();
    expect(bin2hex($fb->flush(mono())))->toBe('ff')->and($fb->front()->getPixel(0, 0))->toBe(1);
});
