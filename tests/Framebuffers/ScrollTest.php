<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\DamageTrackingFramebuffer;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\FramebufferException;
use Surface\Contracts\Framebuffers\Region;
use Surface\Framebuffers\HdrImage;
use Surface\Framebuffers\Native\NativeFramebufferDriver;

/*
 * Framebuffer::scroll(): the region, clipped to the buffer, moves its pixels; what moves past its
 * edge is dropped, the exposed strip keeps its old pixels, nothing outside it changes.
 */

const SCROLL_SIZE = 20;

/** A pattern buffer: pixel (x, y) is RGBA (x, y, 7, 255). */
function scrollPattern(Framebuffer $fb): string
{
    $bytes = '';
    for ($y = 0; $y < SCROLL_SIZE; $y++) {
        for ($x = 0; $x < SCROLL_SIZE; $x++) {
            $bytes .= chr($x).chr($y).chr(7).chr(255);
        }
    }
    $fb->writeRgba8($bytes, SCROLL_SIZE, SCROLL_SIZE);

    return $bytes;
}

/** What scroll() must leave: inside the clipped region the pixel (dx, dy) back, when that is inside too; elsewhere the old pixel. */
function scrolledReference(string $before, Region $region, int $dx, int $dy): string
{
    $clip = $region->intersect(Region::wholeSurface(SCROLL_SIZE, SCROLL_SIZE));
    $after = $before;
    if (is_null($clip)) {
        return $after;
    }
    for ($y = $clip->y; $y < $clip->bottom(); $y++) {
        for ($x = $clip->x; $x < $clip->right(); $x++) {
            if ($clip->contains($x - $dx, $y - $dy)) {
                $at = ($y * SCROLL_SIZE + $x) * 4;
                $from = (($y - $dy) * SCROLL_SIZE + ($x - $dx)) * 4;
                $after = substr_replace($after, substr($before, $from, 4), $at, 4);
            }
        }
    }

    return $after;
}

dataset('scrolling buffers', [
    'full' => [fn (): Framebuffer => (new NativeFramebufferDriver())->full(FormatSpec::rgba8(), SCROLL_SIZE, SCROLL_SIZE)],
    'dirty' => [fn (): Framebuffer => (new NativeFramebufferDriver())->dirty(FormatSpec::rgba8(), SCROLL_SIZE, SCROLL_SIZE)],
]);

it('moves the region\'s pixels and leaves the rest', function (Closure $make, int $dx, int $dy): void {
    $fb = $make();
    $before = scrollPattern($fb);
    $region = new Region(2, 2, 10, 10);

    $fb->scroll($region, $dx, $dy);

    expect(bin2hex($fb->toRgba8()))->toBe(bin2hex(scrolledReference($before, $region, $dx, $dy)));
})->with('scrolling buffers')->with([
    'down' => [0, 3],
    'up' => [0, -3],
    'right' => [3, 0],
    'left' => [-3, 0],
    'diagonal' => [2, -4],
]);

it('clips a region partly outside the buffer', function (Closure $make): void {
    $fb = $make();
    $before = scrollPattern($fb);
    $region = new Region(15, -5, 10, 15);

    $fb->scroll($region, -2, 3);

    expect(bin2hex($fb->toRgba8()))->toBe(bin2hex(scrolledReference($before, $region, -2, 3)));
})->with('scrolling buffers');

it('changes nothing for no move, a region off the buffer or a move past the region', function (Closure $make): void {
    $fb = $make();
    $before = scrollPattern($fb);

    $fb->scroll(new Region(2, 2, 10, 10), 0, 0);
    $fb->scroll(new Region(30, 30, 5, 5), 0, 2);
    $fb->scroll(new Region(2, 2, 10, 10), 0, 10);

    expect(bin2hex($fb->toRgba8()))->toBe(bin2hex($before));
})->with('scrolling buffers');

it('damages the whole region it scrolled', function (): void {
    $fb = (new NativeFramebufferDriver())->dirty(FormatSpec::rgba8(), SCROLL_SIZE, SCROLL_SIZE);
    scrollPattern($fb);
    $fb->beginEpoch();

    $fb->scroll(new Region(2, 2, 10, 10), 0, -3);

    expect($fb)->toBeInstanceOf(DamageTrackingFramebuffer::class);
    $covered = array_reduce($fb->damage(), fn (?Region $all, Region $region): Region => $all?->union($region) ?? $region);
    expect($covered->intersect(new Region(2, 2, 10, 10)))->toEqual(new Region(2, 2, 10, 10));
});

it('refuses to scroll a read-only image and a paged buffer', function (): void {
    $hdr = HdrImage::fromFloats(array_fill(0, 4 * 4, 0.5), 2, 2);
    $paged = (new NativeFramebufferDriver())->paged(FormatSpec::rgba8(), 8, 8, 4);

    expect(fn () => $hdr->scroll(new Region(0, 0, 2, 2), 0, 1))->toThrow(FramebufferException::class)
        ->and(fn () => $paged->scroll(new Region(0, 0, 8, 8), 0, 1))->toThrow(FramebufferException::class, 'A paged framebuffer holds one page at a time: it cannot scroll.');
});

it('scrolls a ring\'s last frame into its back', function (): void {
    $ring = (new NativeFramebufferDriver())->ring(FormatSpec::rgba8(), SCROLL_SIZE, SCROLL_SIZE, 2);
    $before = scrollPattern($ring);
    $ring->present();

    $ring->scroll(new Region(2, 2, 10, 10), 0, 3);

    expect(bin2hex($ring->back()->toRgba8()))->toBe(bin2hex(scrolledReference($before, new Region(2, 2, 10, 10), 0, 3)))
        ->and($ring->age())->toBe(1);
});

it('will not scroll a ring\'s back drawn before its repair', function (): void {
    $ring = (new NativeFramebufferDriver())->ring(FormatSpec::rgba8(), SCROLL_SIZE, SCROLL_SIZE, 2);
    scrollPattern($ring);
    $ring->present();
    $ring->setPixel(0, 0, 1);

    expect(fn () => $ring->scroll(new Region(2, 2, 10, 10), 0, 3))->toThrow(FramebufferException::class, 'repair() comes before drawing');
});

it('scrolls a GL framebuffer through its shadow, uploads the moved part and damages the region', function (): void {
    $gl = new FakeGLFramebuffer(SCROLL_SIZE, SCROLL_SIZE);
    $before = scrollPattern($gl);
    $gl->beginEpoch();
    $gl->uploads = [];

    $gl->scroll(new Region(2, 2, 10, 10), 0, -3);

    $covered = array_reduce($gl->damage(), fn (?Region $all, Region $region): Region => $all?->union($region) ?? $region);
    expect(bin2hex($gl->pixels->toRgba8()))->toBe(bin2hex(scrolledReference($before, new Region(2, 2, 10, 10), 0, -3)))
        ->and($gl->uploads)->not->toBe([])
        ->and($covered->intersect(new Region(2, 2, 10, 10)))->toEqual(new Region(2, 2, 10, 10));
});
