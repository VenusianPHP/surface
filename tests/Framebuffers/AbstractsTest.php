<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\ChannelPalette;
use Surface\Contracts\Framebuffers\ChannelSpec;
use Surface\Contracts\Framebuffers\EInkColor;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\FramebufferException;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\Region;
use Venusian\Surface\Tests\Fixtures\FakeDirtyFramebuffer;
use Venusian\Surface\Tests\Fixtures\FakeePaperFramebuffer;
use Venusian\Surface\Tests\Fixtures\FakeFullFramebuffer;
use Venusian\Surface\Tests\Fixtures\FakePagedFramebuffer;
use Venusian\Surface\Tests\Fixtures\FakeRingFramebuffer;

/*
 * What each kind adds to a pixel store, over a store with no layout (one byte
 * per pixel). The real stores are proven by the fixtures.
 */

function grey(): FormatSpec
{
    return new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B8);
}

/** @return list<array{int, int, int, int}> */
function areas(array $regions): array
{
    return array_map(fn (Region $r): array => [$r->x, $r->y, $r->width, $r->height], $regions);
}

it('puts the whole Framebuffer contract over one store', function (): void {
    $fb = new FakeFullFramebuffer(grey(), 4, 2);
    $fb->setPixel(0, 0, 7)->setSegment(2, -1, 5, 2, 9)->setPixels([[0, 1, 1]])->setRegion([[1, 1]], 2);

    expect(bin2hex($fb->dump()))->toBe('07000909'.'01020000')
        ->and($fb->getPixel(3, 0))->toBe(9)
        ->and(bin2hex($fb->flushRegion(new Region(2, 0, 2, 2), grey())))->toBe('09090000')
        ->and($fb->flush(grey(), true))->toBe([7, 0, 9, 9, 1, 2, 0, 0])
        ->and(bin2hex($fb->clear()->dump()))->toBe('0000000000000000')
        ->and($fb->viewportWidth())->toBe(4)
        ->and($fb->viewportHeight())->toBe(2)
        ->and($fb->hostFormat())->toBe($fb->store()->format())
        ->and($fb->pointer())->toBe(0)
        ->and($fb->preservesContentsOnPresent())->toBeTrue()
        ->and(fn () => $fb->setPixel(4, 0, 1))->toThrow(FramebufferException::class, '(4, 0) is outside a 4x2 framebuffer')
        ->and(fn () => $fb->getPixel(0, 2))->toThrow(FramebufferException::class)
        ->and(fn () => $fb->flushRegion(new Region(3, 0, 2, 1), grey()))->toThrow(FramebufferException::class, 'not inside a 4x2 framebuffer');
});

it('blits through RGBA8, into the target at an offset', function (): void {
    $source = (new FakeFullFramebuffer(grey(), 2, 2))->fill(5);
    $target = new FakeFullFramebuffer(grey(), 4, 2);

    expect(bin2hex($source->blitTo($target, 3, 1)->dump()))->toBe('00000000'.'00000005')
        ->and(bin2hex($target->blitFrom($source, -1, 0)->dump()))->toBe('05000000'.'05000005');
});

it('records what a dirty framebuffer writes, per epoch', function (): void {
    $fb = new FakeDirtyFramebuffer(grey(), 8, 8);
    $fb->setSegment(1, 1, 3, 2, 9)->setPixels([[5, 5, 1], [7, 7, 1]])->setPixel(0, 7, 1);

    expect(areas($fb->damage()))->toBe([[1, 1, 3, 2], [5, 5, 3, 3], [0, 7, 1, 1]])
        ->and(areas($fb->written()))->toBe([[1, 1, 3, 2], [5, 5, 3, 3], [0, 7, 1, 1]])
        ->and($fb->beginEpoch()->damage())->toBe([])
        ->and(areas($fb->fill(1)->damage()))->toBe([[0, 0, 8, 8]])
        ->and(areas($fb->beginEpoch()->setSegment(8, 8, 2, 2, 1)->setPixels([])->damage()))->toBe([]);
});

it('starts an ePaper frame as paper and refuses formats no controller takes', function (): void {
    $mono = new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1);
    $spectra = new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B4, palette: new ChannelPalette(new ChannelSpec(EInkColor::BLACK->value, code: 0), new ChannelSpec(EInkColor::WHITE->value, code: 1), new ChannelSpec(EInkColor::RED->value, code: 3)));
    $planar = new FormatSpec(PixelFormat::PLANAR, BitDepth::B1, palette: new ChannelPalette(new ChannelSpec(EInkColor::BLACK->value, true), new ChannelSpec(EInkColor::RED->value)));

    $fb = new FakeePaperFramebuffer($spectra, 2, 1);

    expect($fb->paper())->toBe(1)
        ->and(bin2hex($fb->dump()))->toBe('0101')
        ->and(bin2hex($fb->fill(3)->clear()->dump()))->toBe('0101')
        ->and($fb->channelDump(EInkColor::RED))->toBe('plane 3')
        ->and((new FakeePaperFramebuffer($mono, 2, 1))->paper())->toBe(1)
        ->and((new FakeePaperFramebuffer($mono, 2, 1))->channelDump(EInkColor::BLACK))->toBe("\x01\x01")
        ->and((new FakeePaperFramebuffer($planar, 2, 1))->paper())->toBe(0)
        ->and((new FakeePaperFramebuffer($planar, 2, 1))->channelDump(EInkColor::RED))->toBe('layer 1')
        ->and(fn () => $fb->channelDump(EInkColor::BLUE))->toThrow(FramebufferException::class, "BLUE is not in this panel's palette")
        ->and(fn () => new FakeePaperFramebuffer(grey(), 2, 1))->toThrow(FramebufferException::class, 'ePaper takes')
        ->and(fn () => new FakeePaperFramebuffer(new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B16), 2, 1))->toThrow(FramebufferException::class, 'ePaper takes');
});

it('holds one page of a taller surface', function (): void {
    $fb = new FakePagedFramebuffer(grey(), 2, 5, 2);
    $picture = fn () => $fb->setSegment(0, 1, 2, 3, 9)->setPixels([[0, 0, 1], [1, 4, 2]]);

    expect($fb->pages())->toBe(3)->and($fb->pageRows())->toBe(2)->and($fb->page())->toBe(0);

    $picture();
    expect(bin2hex($fb->flush(grey())))->toBe('0100'.'0909')
        ->and($fb->getPixel(0, 3))->toBe(0);

    $fb->setPage(2);
    $picture();
    expect(bin2hex($fb->flush(grey())))->toBe('0002')
        ->and(bin2hex($fb->dump()))->toBe('00020000')
        ->and(strlen($fb->toRgba8()))->toBe(2 * 1 * 4)
        ->and(areas([$fb->pageRegion(2)]))->toBe([[0, 4, 2, 1]])
        ->and(bin2hex($fb->flushRegion(new Region(1, 3, 1, 2), grey())))->toBe('02')
        ->and($fb->flushRegion(new Region(0, 0, 2, 2), grey()))->toBe('')
        ->and(bin2hex($fb->fill(7)->dump()))->toBe('07070000')
        ->and($fb->pagesTouching(new Region(0, 1, 1, 3)))->toBe([0, 1])
        ->and($fb->pagesTouching(new Region(0, 9, 1, 1)))->toBe([])
        ->and($fb->damageGranularity()->unit_height)->toBe(2)
        ->and($fb->preservesContentsOnPresent())->toBeFalse()
        ->and(fn () => $fb->setPage(3))->toThrow(FramebufferException::class, 'Page 3 is outside 0..2')
        ->and(fn () => $fb->setPixel(0, 5, 1))->toThrow(FramebufferException::class)
        ->and(fn () => new FakePagedFramebuffer(grey(), 2, 5, 0))->toThrow(FramebufferException::class, 'at least 1')
        ->and(fn () => new FakePagedFramebuffer(new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1), 8, 16, 4))->toThrow(FramebufferException::class, 'multiple of 8');
});

it('runs a swap chain: back and front, holds, age, repair, damage', function (): void {
    $ring = new FakeRingFramebuffer(grey(), 4, 1, 3);

    expect($ring->frames())->toBe(3)->and($ring->serial())->toBe(0)->and($ring->age())->toBe(0)->and($ring->damage())->toBe([]);

    $ring->fill(1)->present();                                    // frame 1
    $held = $ring->hold();
    $ring->fill(2)->present();                                    // frame 2, while a reader holds frame 1

    expect(bin2hex($ring->dump()))->toBe('02020202')
        ->and(bin2hex($held->dump()))->toBe('01010101')
        ->and($ring->frame(1))->toBe($held)
        ->and($ring->age())->toBe(0);                             // the third frame was never presented

    $ring->fill(3)->present();                                    // frame 3; the only free frame is frame 2's

    expect($ring->age())->toBe(2)
        ->and($ring->getPixel(0, 0))->toBe(2)                     // drawing reads the back
        ->and($ring->front()->getPixel(0, 0))->toBe(3);

    $ring->repair();

    expect($ring->age())->toBe(1)->and($ring->getPixel(0, 0))->toBe(3);

    $ring->setPixel(3, 0, 9)->present();                          // frame 4 changes one pixel

    expect(bin2hex($ring->dump()))->toBe('03030309')
        ->and(areas($ring->damage()))->toBe([[3, 0, 1, 1]])
        ->and(areas($ring->damage(2)))->toBe([[0, 0, 4, 1]])
        ->and($ring->damage(4))->toBe([])
        ->and(bin2hex($held->dump()))->toBe('01010101')
        ->and($ring->release($held)->ready())->toBeTrue()
        ->and(fn () => $ring->release($held))->toThrow(FramebufferException::class, 'release() takes a frame')
        ->and(fn () => $ring->setPixel(0, 0, 1)->repair())->toThrow(FramebufferException::class, 'repair() comes before drawing')
        ->and(fn () => new FakeRingFramebuffer(grey(), 4, 1, 1))->toThrow(FramebufferException::class, 'at least two frames');
});

it('leaves a two-frame ring with nothing to draw into while its old front is held', function (): void {
    $ring = new FakeRingFramebuffer(grey(), 4, 1, 2);
    $held = $ring->present()->hold();
    $ring->present();

    expect($ring->ready())->toBeFalse()
        ->and(fn () => $ring->fill(1))->toThrow(FramebufferException::class, 'Every frame but the front is held')
        ->and(fn () => $ring->present())->toThrow(FramebufferException::class)
        ->and($ring->release($held)->ready())->toBeTrue()
        ->and($ring->back())->toBe($held);
});
