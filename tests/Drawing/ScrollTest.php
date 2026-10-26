<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\DrawingException;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Region;
use Surface\Drawing\Velvet\VelvetGE;
use Surface\Framebuffers\Native\NativeFramebufferDriver;
use Surface\NutsAndBolts\Color;
use Surface\Rasterize\Native\NativeRasterizeDriver;

/*
 * Scrolled drawing: scroll() moves the framebuffer's pixels between frames and the next end()
 * damages the region; a frame that does not clear then draws only the exposed strip.
 */

/** Band $band's colour: each band is 10 rows. */
function scrollBand(int $band): Color
{
    return Color::rgb(30 * $band, 255 - 30 * $band, 40);
}

/** Bands $first.. from row $top down to row $bottom, 10 rows each. */
function scrollBands(VelvetGE $velvet, int $top, int $bottom, int $first): void
{
    for ($y = $top, $band = $first; $y < $bottom; $y += 10, $band++) {
        $velvet->fillRect(0, $y, 40, 10, scrollBand($band));
    }
}

function scrollCovers(array $damage, Region $region): bool
{
    $all = array_reduce($damage, fn (?Region $all, Region $part): Region => $all?->union($part) ?? $part);

    return ! is_null($all) && $all->intersect($region) == $region;
}

it('scrolls the last frame and damages the region and what the next frame draws', function (): void {
    $buffer = (new NativeFramebufferDriver())->full(FormatSpec::rgba8(), 40, 80);
    $velvet = new VelvetGE($buffer, new NativeRasterizeDriver());
    $velvet->begin()->clear(Color::rgb(0, 0, 0));
    scrollBands($velvet, 0, 80, 0);
    $velvet->end();

    expect($velvet->canScroll())->toBeTrue();
    $region = new Region(0, 0, 40, 60);
    $velvet->scroll($region, 0, -20);
    $velvet->begin();
    scrollBands($velvet, 40, 60, 6);
    $velvet->end();

    $reference = (new NativeFramebufferDriver())->full(FormatSpec::rgba8(), 40, 80);
    $whole = new VelvetGE($reference, new NativeRasterizeDriver());
    $whole->begin()->clear(Color::rgb(0, 0, 0));
    scrollBands($whole, 0, 60, 2);
    scrollBands($whole, 60, 80, 6);
    $whole->end();

    expect(bin2hex($buffer->toRgba8()))->toBe(bin2hex($reference->toRgba8()))
        ->and(scrollCovers($velvet->damage(), $region))->toBeTrue()
        ->and(scrollCovers($velvet->damage(), new Region(0, 40, 40, 20)))->toBeTrue();
});

it('scrolls a ring\'s last frame, not the older frame its back holds', function (): void {
    $ring = (new NativeFramebufferDriver())->ring(FormatSpec::rgba8(), 40, 80, 2);
    $velvet = new VelvetGE($ring, new NativeRasterizeDriver());
    $velvet->begin()->clear(Color::rgb(0, 0, 0));
    scrollBands($velvet, 0, 80, 0);
    $velvet->end();

    expect($velvet->canScroll())->toBeTrue();
    $velvet->scroll(new Region(0, 0, 40, 60), 0, -20);
    $velvet->begin();
    scrollBands($velvet, 40, 60, 6);
    $velvet->end();

    $reference = (new NativeFramebufferDriver())->full(FormatSpec::rgba8(), 40, 80);
    $whole = new VelvetGE($reference, new NativeRasterizeDriver());
    $whole->begin()->clear(Color::rgb(0, 0, 0));
    scrollBands($whole, 0, 60, 2);
    scrollBands($whole, 60, 80, 6);
    $whole->end();

    expect(bin2hex($ring->toRgba8()))->toBe(bin2hex($reference->toRgba8()));
});

it('repaints the scrolled region of a frame that clears', function (): void {
    $buffer = (new NativeFramebufferDriver())->full(FormatSpec::rgba8(), 40, 80);
    $velvet = new VelvetGE($buffer, new NativeRasterizeDriver());
    $frame = function () use ($velvet): void {
        $velvet->begin()->clear(Color::rgb(0, 0, 0));
        scrollBands($velvet, 0, 80, 0);
        $velvet->end();
    };
    $frame();

    $velvet->scroll(new Region(0, 0, 40, 60), 0, -20);
    $frame();

    $reference = (new NativeFramebufferDriver())->full(FormatSpec::rgba8(), 40, 80);
    $whole = new VelvetGE($reference, new NativeRasterizeDriver());
    $whole->begin()->clear(Color::rgb(0, 0, 0));
    scrollBands($whole, 0, 80, 0);
    $whole->end();

    expect(bin2hex($buffer->toRgba8()))->toBe(bin2hex($reference->toRgba8()))
        ->and(scrollCovers($velvet->damage(), new Region(0, 0, 40, 60)))->toBeTrue();
});

it('cannot scroll a framebuffer that does not keep its frame, and changes nothing', function (): void {
    $paged = (new NativeFramebufferDriver())->paged(FormatSpec::rgba8(), 40, 40, 10);
    $velvet = new VelvetGE($paged, new NativeRasterizeDriver());
    $velvet->begin()->clear(Color::rgb(9, 9, 9))->end();
    $before = $paged->dump();

    expect($velvet->canScroll())->toBeFalse()
        ->and(fn () => $velvet->scroll(new Region(0, 0, 40, 40), 0, 5))->toThrow(DrawingException::class, 'This framebuffer does not keep its frame: it cannot scroll.')
        ->and($paged->dump())->toBe($before);
});

it('refuses to scroll inside an open frame', function (): void {
    $velvet = new VelvetGE((new NativeFramebufferDriver())->full(FormatSpec::rgba8(), 40, 40), new NativeRasterizeDriver());
    $velvet->begin();

    expect(fn () => $velvet->scroll(new Region(0, 0, 40, 40), 0, 5))->toThrow(DrawingException::class, 'A frame is open: scroll() runs between frames.');
});
