<?php

declare(strict_types=1);

use GeneralPurposeIO\Contracts\IntegratedCircuits\DisplayPanel;
use Surface\Contracts\EmbeddedDisplays\EmbeddedDisplayException;
use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\BitOrder;
use Surface\Contracts\Framebuffers\Endianness;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Framebuffers\ScanDirection;
use Surface\EmbeddedDisplays\DirectEDisplay;

function directDisplay(?DisplayPanel $panel = null): DirectEDisplay
{
    return new DirectEDisplay('tft', $panel ?? new FakePipePanel(16, 8, rgb565()), framebuffers(), ['refreshing' => 'epaper', 'addressable' => 'dirty', 'whole' => 'full']);
}

/**
 * The bytes at $spans, read out of the framebuffer's own memory: dump() copies it, so an address is an offset
 * into it from pointer().
 *
 * @param  list<array{int, int}>  $spans
 */
function pipedBytes(Framebuffer $framebuffer, array $spans): string
{
    $memory = $framebuffer->dump();

    return implode('', array_map(fn (array $span): string => substr($memory, $span[0] - $framebuffer->pointer(), $span[1]), $spans));
}

beforeEach(function () {
    if (! class_exists(FbBuffer::class)) {
        $this->markTestSkipped('needs ext-fb');
    }
});

it('pipes the whole frame first, as one span of the framebuffer\'s memory', function () {
    $display = directDisplay();
    $fb = $display->framebuffer();
    $fb->setPixel(5, 3, 0xF800);

    $display->present();

    expect($display->panel()->calls)->toBe([['window', 0, 0, 16, 8]])
        ->and($display->panel()->bus->spans)->toBe([[[$fb->pointer(), 16 * 8 * 2]]])
        ->and(pipedBytes($fb, $display->panel()->bus->spans[0]))->toBe($fb->flush(rgb565()));
});

it('pipes damage afterwards, a span a row, the bytes those flushRegion() returns', function () {
    $display = directDisplay();
    $fb = $display->framebuffer();
    $display->present();

    $fb->setSegment(3, 2, 4, 3, 0x07E0);
    $display->present();

    $bus = $display->panel()->bus;
    $row = fn (int $y): array => [$fb->pointer() + ($y * 16 + 3) * 2, 8];
    expect($display->panel()->calls[1])->toBe(['window', 3, 2, 4, 3])
        ->and($bus->spans[1])->toBe([$row(2), $row(3), $row(4)])
        ->and(pipedBytes($fb, $bus->spans[1]))->toBe($fb->flushRegion(new Region(3, 2, 4, 3), rgb565()));
});

it('pipes a full-width region as one span', function () {
    $display = directDisplay();
    $fb = $display->framebuffer();
    $display->present();

    $fb->setSegment(0, 4, 16, 2, 0x001F);
    $display->present();

    expect($display->panel()->bus->spans[1])->toBe([[$fb->pointer() + 4 * 16 * 2, 2 * 16 * 2]]);
});

it('pipes a ring from its front frame', function () {
    $display = directDisplay();
    $ring = $display->framebuffer('ring', frames: 3);
    $ring->setPixel(1, 1, 0xFFFF);
    $ring->present();
    $display->present();
    $ring->repair();
    $ring->setPixel(9, 6, 0xF800);
    $ring->present();
    $display->present();

    expect($display->panel()->calls[1])->toBe(['window', 9, 6, 1, 1])
        ->and($display->panel()->bus->spans[1])->toBe([[$ring->pointer() + (6 * 16 + 9) * 2, 2]]);
});

it('says what it can pipe', function () {
    $display = directDisplay();

    expect($display->canPipe(framebuffers()->driver('extended')->dirty(rgb565(), 16, 8)))->toBeTrue()
        ->and($display->canPipe(framebuffers()->driver('native')->dirty(rgb565(), 16, 8)))->toBeFalse();
});

it('refuses a framebuffer it cannot pipe, naming why', function (Closure $framebuffer, string $why) {
    expect(fn () => directDisplay()->bind($framebuffer()))->toThrow(EmbeddedDisplayException::class, $why);
})->with([
    'native' => [fn () => framebuffers()->driver('native')->dirty(rgb565(), 16, 8), 'not in C memory'],
    'other format' => [fn () => framebuffers()->driver('extended')->dirty(FormatSpec::rgba8(), 16, 8), "not the panel's"],
    'paged' => [fn () => framebuffers()->driver('extended')->paged(rgb565(), 16, 8, 4), 'paged'],
]);

it('refuses a panel format that packs pixels into part of a byte', function () {
    $mono = new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1, ScanDirection::TOP_TO_BOTTOM, BitOrder::MSB_FIRST);

    directDisplay(new FakePipePanel(16, 8, $mono))->framebuffer();
})->throws(EmbeddedDisplayException::class, 'part of a byte');

it('refuses the native driver and paged framebuffers when asked to mint them', function (array $args, string $why) {
    expect(fn () => directDisplay()->framebuffer(...$args))->toThrow(EmbeddedDisplayException::class, $why);
})->with([
    'native driver' => [['driver' => 'native'], "'native' driver"],
    'paged' => [['kind' => 'paged', 'page_rows' => 4], 'paged'],
]);

it('refuses a panel that is not pipeable, and one whose bus cannot write from memory', function (Closure $panel, string $why) {
    expect(fn () => directDisplay($panel()))->toThrow(EmbeddedDisplayException::class, $why);
})->with([
    'window panel' => [fn () => new FakeWindowPanel(16, 8, rgb565()), 'not a PipeablePanel'],
    'no memory bus' => [fn () => new FakePipePanel(16, 8, rgb565(), null), 'cannot write from memory'],
]);

it('refuses to pipe after the panel changed format, until it is bound again', function () {
    $panel = new FakePipePanel(16, 8, rgb565());
    $display = directDisplay($panel);
    $display->framebuffer();
    $panel->format = new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B16, endianness: Endianness::LSB);

    expect(fn () => $display->present())->toThrow(EmbeddedDisplayException::class, 'format changed')
        ->and($display->framebuffer()->hostFormat()->equals($panel->format))->toBeTrue();
});

it('latches a short write as a fault', function () {
    $panel = new FakePipePanel(16, 8, rgb565());
    $panel->bus->answer = 10;
    $display = directDisplay($panel);
    $display->framebuffer();

    $display->present();

    expect($display->faulted())->toBeTrue()
        ->and($display->fault()->getMessage())->toContain('piped 10 of 256 bytes');
});
