<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Framebuffers\FramebufferException;
use Surface\Contracts\Framebuffers\Region;
use Venusian\Surface\Tests\Support\Framebuffers\Formats;

it('needs at least two frames', function (FramebufferDriver $driver): void {
    expect(fn () => $driver->ring(Formats::mono(), 8, 1, 1))->toThrow(FramebufferException::class, 'at least two frames');
})->with('framebuffer drivers');

it('draws into the back and drains the front', function (FramebufferDriver $driver): void {
    $ring = $driver->ring(Formats::mono(), 8, 1, 2);
    $ring->fill(1);

    expect(bin2hex($ring->flush(Formats::mono())))->toBe('00')
        ->and($ring->getPixel(0, 0))->toBe(1)
        ->and(bin2hex($ring->back()->dump()))->toBe('ff')
        ->and(bin2hex($ring->front()->dump()))->toBe('00')
        ->and(bin2hex($ring->toRgba8()))->toBe(str_repeat('000000ff', 8));

    $ring->present();

    expect(bin2hex($ring->dump()))->toBe('ff')
        ->and($ring->front()->getPixel(0, 0))->toBe(1)
        ->and($ring->getPixel(0, 0))->toBe(0)
        ->and($ring->serial())->toBe(1);
})->with('framebuffer drivers');

it('never draws into a frame a reader holds', function (FramebufferDriver $driver): void {
    $ring = $driver->ring(Formats::mono(), 8, 1, 3);
    $held = $ring->fill(1)->present()->hold();

    foreach ([0x80, 0x40, 0x20, 0x10] as $bits) {
        $ring->clear()->setPixels(array_map(fn (int $x): array => [$x, 0, ($bits >> (7 - $x)) & 1], range(0, 7)))->present();
        expect(bin2hex($held->dump()))->toBe('ff')
            ->and($ring->ready())->toBeTrue();
    }

    expect(bin2hex($ring->dump()))->toBe('10')
        ->and($ring->serial())->toBe(5)
        ->and($ring->frame(4))->toBe($held)
        ->and($ring->release($held)->ready())->toBeTrue();
})->with('framebuffer drivers');

it('makes a two-frame drawer wait for the reader', function (FramebufferDriver $driver): void {
    $ring = $driver->ring(Formats::mono(), 8, 1, 2);
    $held = $ring->fill(1)->present()->hold();
    $ring->present();

    expect($ring->ready())->toBeFalse()
        ->and(fn () => $ring->setPixel(0, 0, 1))->toThrow(FramebufferException::class, 'Every frame but the front is held')
        ->and(fn () => $ring->back())->toThrow(FramebufferException::class)
        ->and(fn () => $ring->present())->toThrow(FramebufferException::class)
        ->and(fn () => $ring->age())->toThrow(FramebufferException::class)
        ->and(bin2hex($ring->flush(Formats::mono())))->toBe('00');

    $ring->release($held);

    expect($ring->ready())->toBeTrue()
        ->and($ring->age())->toBe(2)
        ->and($ring->back())->toBe($held);
})->with('framebuffer drivers');

it('counts holds, and refuses a release it did not hand out', function (FramebufferDriver $driver): void {
    $ring = $driver->ring(Formats::mono(), 8, 1, 2);
    $first = $ring->hold();
    $second = $ring->hold();
    $ring->present();

    expect($second)->toBe($first)
        ->and($ring->ready())->toBeFalse()
        ->and($ring->release($first)->ready())->toBeFalse()
        ->and($ring->release($second)->ready())->toBeTrue()
        ->and(fn () => $ring->release($second))->toThrow(FramebufferException::class, 'release() takes a frame hold() answered')
        ->and(fn () => $ring->release($driver->full(Formats::mono(), 8, 1)))->toThrow(FramebufferException::class);
})->with('framebuffer drivers');

it('reports the back\'s age and repairs it from the front', function (FramebufferDriver $driver): void {
    $ring = $driver->ring(Formats::mono(), 8, 2, 2);

    expect($ring->age())->toBe(0);

    $ring->fill(0)->setPixel(0, 0, 1)->present();     // frame 1: 80 00
    $ring->fill(0)->setPixel(0, 0, 1)->setPixel(7, 1, 1)->present();   // frame 2: 80 01

    expect($ring->age())->toBe(2)
        ->and(bin2hex($ring->back()->dump()))->toBe('8000');

    $ring->repair();

    expect($ring->age())->toBe(1)
        ->and(bin2hex($ring->back()->dump()))->toBe('8001')
        ->and($ring->back()->damage())->toBe([]);

    $ring->setPixel(1, 0, 1)->present();              // frame 3 changes one pixel

    expect(bin2hex($ring->dump()))->toBe('c001')
        ->and(array_map(fn (Region $r): array => [$r->x, $r->y, $r->width, $r->height], $ring->damage()))->toBe([[1, 0, 1, 1]])
        ->and(fn () => $ring->setPixel(0, 0, 0)->repair())->toThrow(FramebufferException::class, 'repair() comes before drawing');
})->with('framebuffer drivers');

it('answers what a reader that last drained a serial must send', function (FramebufferDriver $driver): void {
    $rects = fn (array $regions): array => array_map(fn (Region $r): array => [$r->x, $r->y, $r->width, $r->height], $regions);
    $ring = $driver->ring(Formats::rgb565(), 8, 8, 3);

    expect($ring->damage())->toBe([]);

    $ring->fill(0)->present();                               // 1: everything
    $ring->repair()->setPixel(1, 1, 5)->present();           // 2: one pixel
    $ring->repair()->setSegment(4, 4, 2, 2, 5)->present();   // 3: one rect

    expect($rects($ring->damage()))->toBe([[4, 4, 2, 2]])
        ->and($rects($ring->damage(1)))->toBe([[1, 1, 1, 1], [4, 4, 2, 2]])
        ->and($rects($ring->damage(0)))->toBe([[0, 0, 8, 8]])
        ->and($ring->damage(3))->toBe([])
        ->and($ring->damage(9))->toBe([]);

    $ring->setPixel(0, 0, 1)->present();                     // 4: not repaired, so it also carries what the back had missed

    expect($rects($ring->damage()))->toBe([[4, 4, 2, 2], [0, 0, 2, 2]])
        ->and($rects($ring->damage(0)))->toBe([[0, 0, 8, 8]]);   // serial 1 has left the record
})->with('framebuffer drivers');

it('keeps finished frames as history beyond the third', function (FramebufferDriver $driver): void {
    $ring = $driver->ring(Formats::mono(), 8, 1, 4);
    foreach ([0x01, 0x02, 0x04] as $bits) {
        $ring->clear()->setPixel(8 - (int) log($bits, 2) - 1, 0, 1)->present();
    }

    expect(bin2hex($ring->frame(0)->dump()))->toBe('04')
        ->and(bin2hex($ring->frame(1)->dump()))->toBe('02')
        ->and(bin2hex($ring->frame(2)->dump()))->toBe('01')
        ->and($ring->frame(3))->toBeNull()
        ->and($ring->frame(-1))->toBeNull()
        ->and($ring->age())->toBe(0);

    $ring->present();

    expect($ring->age())->toBe(4)
        ->and(bin2hex($ring->frame(1)->dump()))->toBe('04')
        ->and($ring->frame(3))->toBeNull();
})->with('framebuffer drivers');
