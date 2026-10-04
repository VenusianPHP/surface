<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\EInkColor;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Framebuffers\FramebufferException;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\Region;
use Venusian\Surface\Tests\Support\Framebuffers\Formats;

/** @return list<array{int, int, int, int}> */
function rects(array $regions): array
{
    return array_map(fn (Region $r): array => [$r->x, $r->y, $r->width, $r->height], $regions);
}

it('spins up, draws and drains with no rendering engine', function (FramebufferDriver $driver): void {
    $fb = $driver->full(FormatSpec::rgba8(), 4, 2);
    $fb->fill(0x000000FF)->setSegment(1, 0, 2, 1, 0xFF8000FF)->setPixel(3, 1, 0x00FF00FF);

    expect(bin2hex($fb->flush(FormatSpec::rgba8())))->toBe('000000ff'.'ff8000ff'.'ff8000ff'.'000000ff'.'000000ff'.'000000ff'.'000000ff'.'00ff00ff')
        ->and(bin2hex($fb->flush(FormatSpec::bgra8())))->toBe('000000ff'.'0080ffff'.'0080ffff'.'000000ff'.'000000ff'.'000000ff'.'000000ff'.'00ff00ff')
        ->and(bin2hex($fb->flush(Formats::mono())))->toBe('6010')
        ->and($fb->flush(Formats::rgb565(), true))->toBe([0, 0, 0xFC, 0x00, 0xFC, 0x00, 0, 0, 0, 0, 0, 0, 0, 0, 0x07, 0xE0])
        ->and($fb->viewportWidth())->toBe(4)
        ->and($fb->hostFormat()->equals(FormatSpec::rgba8()))->toBeTrue()
        ->and($fb->preservesContentsOnPresent())->toBeTrue();
})->with('framebuffer drivers');

it('names itself and mints the five kinds', function (FramebufferDriver $driver): void {
    expect($driver->driver())->toBeIn(['native', 'extended'])
        ->and($driver->dirty(Formats::mono(), 8, 8)->damage())->toBe([])
        ->and($driver->epaper(Formats::mono(), 8, 8)->paper())->toBe(1)
        ->and($driver->paged(Formats::mono(), 8, 16, 8)->pages())->toBe(2)
        ->and($driver->ring(Formats::mono(), 8, 8, 3)->frames())->toBe(3);
})->with('framebuffer drivers');

it('throws on a pixel outside the surface and clips a segment instead', function (FramebufferDriver $driver): void {
    $fb = $driver->full(Formats::mono(), 8, 2);
    $fb->setSegment(6, -1, 5, 2, 1);

    expect(bin2hex($fb->dump()))->toBe('0300')
        ->and($fb->setSegment(8, 0, 4, 4, 1)->dump())->toBe("\x03\x00")
        ->and(fn () => $fb->setPixel(8, 0, 1))->toThrow(FramebufferException::class, '(8, 0) is outside a 8x2 framebuffer')
        ->and(fn () => $fb->getPixel(0, -1))->toThrow(FramebufferException::class)
        ->and(fn () => $fb->flushRegion(new Region(4, 0, 5, 1), Formats::mono()))->toThrow(FramebufferException::class, 'not inside a 8x2 framebuffer')
        ->and(fn () => $fb->flushRegion(new Region(0, 0, 0, 1), Formats::mono()))->toThrow(FramebufferException::class)
        ->and(fn () => $fb->dump(1))->toThrow(FramebufferException::class, 'Layer 1 is outside 0..0');
})->with('framebuffer drivers');

it('refuses a pixel list whole when any entry is outside or malformed', function (FramebufferDriver $driver): void {
    $fb = $driver->dirty(Formats::mono(), 8, 1);

    expect(fn () => $fb->setPixels([[0, 0, 1], [8, 0, 1]]))->toThrow(FramebufferException::class, '(8, 0) is outside')
        ->and(fn () => $fb->setPixels([[0, 0, 1], [1, 0]]))->toThrow(FramebufferException::class, 'Pixel 1 is not [x, y, value]')
        ->and(fn () => $fb->setRegion([[0, 0], [0, 1]], 1))->toThrow(FramebufferException::class, '(0, 1) is outside')
        ->and(fn () => $fb->setRegion([[0, 0], ['a', 0]], 1))->toThrow(FramebufferException::class, 'Pixel 1 is not [x, y]')
        ->and(bin2hex($fb->dump()))->toBe('00')
        ->and($fb->damage())->toBe([])
        ->and($fb->setPixels([])->damage())->toBe([]);
})->with('framebuffer drivers');

it('records a bulk write as its bounding box on a dirty buffer', function (FramebufferDriver $driver): void {
    $fb = $driver->dirty(new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B8), 8, 8);
    $fb->beginEpoch()->setSegment(1, 1, 3, 2, 9)->setPixels([[5, 5, 1], [7, 7, 1]])->setRegion([[0, 7], [0, 6]], 4);

    expect(rects($fb->damage()))->toBe([[1, 1, 3, 2], [5, 5, 3, 3], [0, 6, 1, 2]])
        ->and(rects($fb->beginEpoch()->damage()))->toBe([])
        ->and(rects($fb->blitFrom($driver->full(Formats::mono(), 2, 2), 7, 7)->damage()))->toBe([[7, 7, 1, 1]])
        ->and(rects($fb->blitFrom($driver->full(Formats::mono(), 2, 2), 8, 8)->damage()))->toBe([[7, 7, 1, 1]]);
})->with('framebuffer drivers');

it('blits one buffer into another through RGBA8, clipped to the target', function (FramebufferDriver $driver): void {
    $source = $driver->full(Formats::rgb565(), 2, 2)->fill(0xFFFF);
    $target = $driver->full(Formats::mono(), 8, 2);

    expect(bin2hex($source->blitTo($target, 7, 1)->dump()))->toBe('0001')
        ->and(bin2hex($target->blitFrom($source, -1, 0)->dump()))->toBe('8081');
})->with('framebuffer drivers');

it('holds an ePaper frame in the controller\'s layout and dumps one ink', function (FramebufferDriver $driver): void {
    $planar = $driver->epaper(Formats::planarBwr(), 8, 1);
    $planar->setPixel(7, 0, 2)->setPixel(0, 0, 1);
    $packed = $driver->epaper(Formats::spectra6(), 8, 1);
    $packed->setPixel(0, 0, 3);

    expect(bin2hex($planar->channelDump(EInkColor::RED)))->toBe('01')
        ->and(bin2hex($planar->channelDump(EInkColor::BLACK)))->toBe('7f')      // the inverted plane as stored
        ->and(bin2hex($planar->dump(1)))->toBe('01')
        ->and($planar->paper())->toBe(0)
        ->and(bin2hex($packed->channelDump(EInkColor::RED)))->toBe('80')
        ->and(bin2hex($packed->channelDump(EInkColor::WHITE)))->toBe('7f')
        ->and(fn () => $packed->channelDump(EInkColor::ORANGE))->toThrow(FramebufferException::class, "ORANGE is not in this panel's palette")
        ->and(fn () => $driver->epaper(Formats::mono(), 8, 1)->channelDump(EInkColor::RED))->toThrow(FramebufferException::class, 'single-ink mono')
        ->and(fn () => $driver->epaper(Formats::rgb565(), 8, 1))->toThrow(FramebufferException::class, 'ePaper takes')
        ->and(fn () => $driver->epaper(new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B2), 8, 1))->toThrow(FramebufferException::class, 'ePaper takes');
})->with('framebuffer drivers');

it('pages a picture: writes off the page drop, reads off it answer 0, drains give the page', function (FramebufferDriver $driver): void {
    $fb = $driver->paged(Formats::mono(), 8, 12, 8);
    $picture = fn () => $fb->setSegment(0, 6, 8, 4, 1)->setPixels([[0, 0, 1], [7, 11, 1]])->setRegion([[1, 0], [6, 11]], 1);

    $fb->setPage(0);
    $picture();
    expect(bin2hex($fb->flush(Formats::mono())))->toBe('c00000000000ffff')
        ->and($fb->getPixel(0, 8))->toBe(0)
        ->and(strlen($fb->toRgba8()))->toBe(8 * 8 * 4);

    $fb->setPage(1);
    $picture();
    expect(bin2hex($fb->flush(Formats::mono())))->toBe('ffff0003')
        ->and($fb->getPixel(0, 8))->toBe(1)
        ->and($fb->getPixel(0, 0))->toBe(0)
        ->and(rects([$fb->pageRegion(1)]))->toBe([[0, 8, 8, 4]])
        ->and(bin2hex($fb->flushRegion(new Region(0, 6, 8, 4), Formats::mono())))->toBe('ffff')
        ->and($fb->flushRegion(new Region(0, 0, 8, 2), Formats::mono()))->toBe('')
        ->and(strlen($fb->toRgba8()))->toBe(8 * 4 * 4)
        ->and(strlen($fb->dump()))->toBe(8)
        ->and(bin2hex($fb->fill(1)->dump()))->toBe('ffffffff00000000')
        ->and($fb->pagesTouching(new Region(0, 7, 1, 2)))->toBe([0, 1])
        ->and($fb->pagesTouching(new Region(0, 40, 1, 2)))->toBe([])
        ->and($fb->preservesContentsOnPresent())->toBeFalse();
})->with('framebuffer drivers');

it('refuses page rows a host cannot page by, and a page that is not there', function (FramebufferDriver $driver): void {
    $pages = new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1);

    expect(fn () => $driver->paged($pages, 8, 16, 4))->toThrow(FramebufferException::class, 'multiple of 8')
        ->and(fn () => $driver->paged(Formats::mono(), 8, 16, 0))->toThrow(FramebufferException::class, 'at least 1')
        ->and($driver->paged($pages, 8, 16, 16)->pages())->toBe(1)
        ->and(fn () => $driver->paged(Formats::mono(), 8, 16, 8)->setPage(2))->toThrow(FramebufferException::class, 'Page 2 is outside 0..1')
        ->and(fn () => $driver->paged(Formats::mono(), 8, 16, 8)->setPixel(0, 16, 1))->toThrow(FramebufferException::class)
        ->and(fn () => $driver->paged(Formats::mono(), 8, 16, 8)->setPixels([[0, 0, 1], [0, 16, 1]]))->toThrow(FramebufferException::class);
})->with('framebuffer drivers');

it('blits a page to where it sits, and into a page only what lands on it', function (FramebufferDriver $driver): void {
    $paged = $driver->paged(Formats::mono(), 8, 4, 2);
    $paged->setPage(1)->fill(1);
    $whole = $driver->full(Formats::mono(), 8, 4);
    $paged->blitTo($whole);

    expect(bin2hex($whole->dump()))->toBe('0000ffff');

    $paged->setPage(0)->blitFrom($whole);
    expect(bin2hex($paged->dump()))->toBe('0000');
    $paged->setPage(1)->blitFrom($whole, 4, -1);
    expect(bin2hex($paged->dump()))->toBe('0f00');   // the source's row 3 lands on row 2; nothing lands on row 3
})->with('framebuffer drivers');
