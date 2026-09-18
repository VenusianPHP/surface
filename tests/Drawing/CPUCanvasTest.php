<?php

use Surface\Contracts\Drawing\CPUEngine;
use Surface\Contracts\Drawing\CPUHost;
use Surface\Contracts\Drawing\Drawing2D;
use Surface\Contracts\Drawing\Frame;
use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\NativeWindows\Views\Color;
use Surface\Drawing\Canvases\DirtyCanvas;
use Surface\Drawing\Canvases\FullCanvas;
use Surface\Drawing\Rasterizer;
use Surface\Framebuffers\Php\PhpFramebufferDriver;

function ssd1306Host(): CPUHost
{
    return new CPUHost(8, 16, new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1));
}

function fullCanvas(): FullCanvas
{
    $host = ssd1306Host();

    return new FullCanvas(CPUEngine::FULL, (new PhpFramebufferDriver())->full($host->format, 8, 16), $host);
}

function dirtyCanvas(): DirtyCanvas
{
    $host = ssd1306Host();

    return new DirtyCanvas(CPUEngine::DIRTY, (new PhpFramebufferDriver())->dirty($host->format, 8, 16), $host);
}

it('skips without a hook, then runs one frame with a Frame at scale 1', function () {
    $c = fullCanvas();
    expect($c->renderFrame())->toBeFalse();

    $seen = null;
    $c->onDraw(function (Drawing2D $g, Frame $f) use (&$seen) { $seen = $f; $g->fillRect(0.0, 0.0, 8.0, 1.0, Color::hex('#fff')); });

    expect($c->renderFrame())->toBeTrue()
        ->and($seen->width)->toBe(8)->and($seen->height)->toBe(16)->and($seen->scale)->toBe(1.0)->and($seen->index)->toBe(0)
        ->and($c->drawing())->toBeInstanceOf(Rasterizer::class)
        ->and($c->engine())->toBe(CPUEngine::FULL)
        ->and($c->drawableSize())->toBe([8, 16])
        ->and(bin2hex($c->flush()))->toBe('01010101010101010000000000000000');
});

it('on-demand frames run once per redraw()', function () {
    $c = fullCanvas()->setContinuous(false)->onDraw(fn () => null);

    expect($c->renderFrame())->toBeFalse();
    $c->redraw();
    expect($c->renderFrame())->toBeTrue()->and($c->renderFrame())->toBeFalse();
});

it('a preserving canvas keeps last frame; the clear colour is applied once at attach', function () {
    $c = fullCanvas();
    expect(bin2hex($c->flush()))->toBe(str_repeat('00', 16));   // black at attach → mono 0
    $c->onDraw(fn (Drawing2D $g) => $g->fillRect(0.0, 0.0, 1.0, 1.0, Color::hex('#fff')))->renderFrame();
    $c->onDraw(fn () => null)->renderFrame();

    expect($c->flush()[0])->toBe("\x01")
        ->and($c->damage())->toEqual([Region::wholeSurface(8, 16)]);
});

it('a hook exception propagates after present', function () {
    $c = fullCanvas()->onDraw(fn () => throw new RuntimeException('sketch bug'));

    expect(fn () => $c->renderFrame())->toThrow(RuntimeException::class, 'sketch bug')
        ->and(fn () => $c->renderFrame())->toThrow(RuntimeException::class);   // and the loop is still alive
});

it('dirty answers this frame\'s damage, snapped, until the next frame', function () {
    $c = dirtyCanvas();
    $c->onDraw(fn (Drawing2D $g) => $g->fillRect(2.0, 9.0, 1.0, 1.0, Color::hex('#fff')))->renderFrame();

    expect($c->damage())->toEqual([new Region(0, 8, 8, 8)])
        ->and(bin2hex($c->flushRegion(new Region(0, 8, 8, 8))))->toBe('0000020000000000');
    $c->onDraw(fn () => null)->renderFrame();
    expect($c->damage())->toBe([]);
});

it('flush to another spec transcodes; rgba8 is the whole target', function () {
    $c = fullCanvas()->onDraw(fn (Drawing2D $g) => $g->fillRect(0.0, 0.0, 8.0, 16.0, Color::hex('#fff')));
    $c->renderFrame();

    expect(bin2hex($c->flush(new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1))))->toBe(str_repeat('ff', 16))
        ->and(strlen($c->rgba8()))->toBe(8 * 16 * 4)
        ->and($c->flush(as_array: true))->toBe(array_fill(0, 16, 255));
});
