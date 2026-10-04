<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Framebuffers\FramebufferException;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Framebuffers\Spans;
use Venusian\Surface\Tests\Support\Framebuffers\Formats;

/** One 0xRRGGBBAA blended over another with the spec's integer rule. */
function blendReference(int $source, int $destination, int $coverage): int
{
    $alpha = intdiv(($source & 0xFF) * $coverage + 127, 255);
    if ($alpha === 0) {
        return $destination;
    }
    if ($alpha === 255) {
        return $source;
    }
    $out = 0;
    foreach ([24, 16, 8] as $shift) {
        $out |= intdiv((($source >> $shift) & 0xFF) * $alpha + (($destination >> $shift) & 0xFF) * (255 - $alpha) + 127, 255) << $shift;
    }

    return $out | intdiv(255 * $alpha + ($destination & 0xFF) * (255 - $alpha) + 127, 255);
}

it('blends every pixel of an RGBA8 span by the integer source-over rule', function (FramebufferDriver $driver): void {
    $random = new Random\Randomizer(new Random\Engine\Mt19937(36));
    $buffer = $driver->full(FormatSpec::rgba8(), 16, 16);
    $before = [];
    for ($y = 0; $y < 16; $y++) {
        for ($x = 0; $x < 16; $x++) {
            $before[$y][$x] = $random->getInt(0, 0xFFFFFFFF);
            $buffer->setPixel($x, $y, $before[$y][$x]);
        }
    }
    $colours = [];
    $spans = '';
    for ($y = 0; $y < 16; $y++) {
        $coverage = $random->getInt(0, 255);
        $spans .= Spans::pack($y, 0, 16, $coverage);
        $colours[$y] = $coverage;
    }
    $colour = $random->getInt(0, 0xFFFFFFFF);

    $buffer->paintSpans($spans, $colour);

    for ($y = 0; $y < 16; $y++) {
        for ($x = 0; $x < 16; $x++) {
            expect($buffer->getPixel($x, $y))->toBe(blendReference($colour, $before[$y][$x], $colours[$y]), "({$x}, {$y})");
        }
    }
})->with('framebuffer drivers');

it('refuses a whole span list when any span is empty or outside, writing nothing', function (FramebufferDriver $driver, string $spans, string $why): void {
    $buffer = $driver->dirty(Formats::rgb565(), 8, 4);
    $buffer->beginEpoch();

    expect(fn () => $buffer->paintSpans($spans, 0xFFFFFFFF))->toThrow(FramebufferException::class, $why)
        ->and(bin2hex($buffer->dump()))->toBe(str_repeat('00', 64))
        ->and($buffer->damage())->toBe([]);
})->with('framebuffer drivers')->with([
    'a right edge past the width' => [Spans::pack(0, 0, 2).Spans::pack(1, 5, 4), 'Span 1 is empty or not inside a 8x4 framebuffer'],
    'a row past the height' => [Spans::pack(0, 0, 2).Spans::pack(4, 0, 1), 'Span 1 is empty or not inside a 8x4 framebuffer'],
    'a span of length 0' => [Spans::pack(0, 0, 2).pack('vvvC', 1, 1, 0, 255), 'Span 1 is empty or not inside a 8x4 framebuffer'],
    'bytes that are not whole spans' => [Spans::pack(0, 0, 2).'xyz', 'whole number of 7-byte spans, got 10 bytes'],
]);

it('refuses a colour that is not 0xRRGGBBAA', function (FramebufferDriver $driver, int $colour): void {
    expect(fn () => $driver->full(FormatSpec::rgba8(), 2, 2)->paintSpans(Spans::pack(0, 0, 1), $colour))->toThrow(FramebufferException::class, 'takes a colour 0xRRGGBBAA');
})->with('framebuffer drivers')->with([-1, 0x100000000]);

it('takes an empty list as nothing to do', function (FramebufferDriver $driver): void {
    $buffer = $driver->dirty(FormatSpec::rgba8(), 2, 2);
    $buffer->beginEpoch();
    $buffer->paintSpans('', 0xFFFFFFFF);

    expect($buffer->damage())->toBe([])
        ->and($buffer->store()->paintSpans('', 0xFFFFFFFF))->toBeNull()
        ->and($buffer->store()->paintSpans(Spans::pack(1, 0, 2, 9), 0x00000000))->toEqual(new Region(0, 1, 2, 1));
})->with('framebuffer drivers');

it('refuses spans outside the virtual surface of a paged buffer, even on rows it would drop', function (FramebufferDriver $driver): void {
    $paged = $driver->paged(FormatSpec::rgba8(), 4, 4, 2);
    $paged->setPage(0);

    expect(fn () => $paged->paintSpans(Spans::pack(0, 0, 1).Spans::pack(4, 0, 1), 0xFFFFFFFF))->toThrow(FramebufferException::class, 'Span 1 is empty or not inside a 4x4 framebuffer')
        ->and(bin2hex($paged->dump()))->toBe(str_repeat('00', 32));
})->with('framebuffer drivers');
