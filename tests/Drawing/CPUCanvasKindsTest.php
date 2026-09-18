<?php

use Surface\Contracts\Drawing\CPUEngine;
use Surface\Contracts\Drawing\CPUHost;
use Surface\Contracts\Drawing\Drawing2D;
use Surface\Contracts\Drawing\DrawingException;
use Surface\Contracts\Drawing\Frame;
use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\ChannelPalette;
use Surface\Contracts\Framebuffers\ChannelSpec;
use Surface\Contracts\Framebuffers\EInkColor;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\NativeWindows\Views\Color;
use Surface\Drawing\Canvases\EPaperCanvas;
use Surface\Drawing\Canvases\NFramesCanvas;
use Surface\Drawing\Canvases\PagedCanvas;
use Surface\Framebuffers\Php\PhpFramebufferDriver;

it('epaper defaults its clear colour to white paper', function () {
    $spec = new FormatSpec(PixelFormat::PLANAR, BitDepth::B1, palette: new ChannelPalette(new ChannelSpec(EInkColor::BLACK->value, true), new ChannelSpec(EInkColor::RED->value)));
    $host = new CPUHost(8, 1, $spec);
    $c = new EPaperCanvas(CPUEngine::EPAPER, (new PhpFramebufferDriver())->epaper($spec, 8, 1), $host);

    expect(bin2hex($c->flush()))->toBe('ff00');   // black plane inverted: all paper; red plane empty
    $c->onDraw(fn (Drawing2D $g) => $g->fillRect(0.0, 0.0, 1.0, 1.0, Color::hex('#f00')))->renderFrame();
    expect(bin2hex($c->flush()))->toBe('ff80')->and($c->damage())->toEqual([Region::wholeSurface(8, 1)]);
});

it('paged runs the hook once per page, clipped, and streams each page to the sink', function () {
    $spec = new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1);
    $host = new CPUHost(8, 16, $spec, page_rows: 8);
    $c = new PagedCanvas(CPUEngine::PAGED, (new PhpFramebufferDriver())->paged($spec, 8, 16, 8), $host);
    $calls = 0;
    $pages = [];
    $c->onDraw(function (Drawing2D $g, Frame $f) use (&$calls) { $calls++; $g->fillRect(0.0, 0.0, 8.0, 9.0, Color::hex('#fff')); })
      ->onPage(function (Region $page, string|array $bytes) use (&$pages) { $pages[] = [$page->y, bin2hex($bytes)]; });

    expect($c->renderFrame())->toBeTrue()
        ->and($calls)->toBe(2)
        ->and($pages)->toBe([[0, 'ffffffffffffffff'], [8, '0101010101010101']])
        ->and($c->damage())->toEqual([new Region(0, 0, 8, 8), new Region(0, 8, 8, 8)]);
});

it('paged flush re-runs the pure hook and assembles; rgba8 too; other specs refuse', function () {
    $spec = new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1);
    $host = new CPUHost(8, 16, $spec);
    $c = new PagedCanvas(CPUEngine::PAGED, (new PhpFramebufferDriver())->paged($spec, 8, 16, 8), $host);
    $c->onDraw(fn (Drawing2D $g) => $g->fillRect(0.0, 8.0, 8.0, 8.0, Color::hex('#fff')));

    expect(bin2hex($c->flush()))->toBe('0000000000000000ffffffffffffffff')
        ->and($c->flush(as_array: true))->toHaveCount(16)
        ->and(strlen($c->rgba8()))->toBe(8 * 16 * 4)
        ->and(substr(bin2hex($c->rgba8()), 0, 8))->toBe('000000ff')
        ->and(fn () => $c->flush(new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1)))->toThrow(DrawingException::class);
});

it('nframes clears the back frame, flips on present, and flush reads the front', function () {
    $spec = new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B8);
    $host = new CPUHost(2, 1, $spec, frames: 2);
    $c = new NFramesCanvas(CPUEngine::NFRAMES, (new PhpFramebufferDriver())->ring($spec, 2, 1, 2), $host);
    $c->setClearColor(Color::hex('#fff'));
    $c->onDraw(fn (Drawing2D $g) => $g->fillRect(0.0, 0.0, 1.0, 1.0, Color::hex('#000')));

    expect(bin2hex($c->flush()))->toBe('0000');
    $c->renderFrame();
    expect(bin2hex($c->flush()))->toBe('00ff')->and($c->damage())->toEqual([Region::wholeSurface(2, 1)]);
    $c->onDraw(fn () => null)->renderFrame();
    expect(bin2hex($c->flush()))->toBe('ffff');
});
