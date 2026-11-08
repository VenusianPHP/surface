<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Framebuffers\FramebufferException;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Framebuffers\Spans;
use Surface\Framebuffers\Extended\ExtendedFullFramebuffer;
use Surface\Framebuffers\Native\NativeFullFramebuffer;
use Venusian\Surface\Tests\Support\Framebuffers\Formats;
use Venusian\Surface\Tests\Support\Framebuffers\Rgba8Source;

/*
 * paintMasks() paints what paintSpans() paints for the same runs: per mask in order, per row inside the clip, a span per
 * run of one coverage above 0, cut to the clip. Held to it on every kind and store, and native to extended in every format.
 */

/**
 * @param  list<array{string, int, int, int}>  $masks
 */
function masksAsSpans(array $masks, Region $clip): string
{
    $spans = '';
    foreach ($masks as [$coverage, $width, $left, $top]) {
        for ($row = 0, $rows = intdiv(strlen($coverage), $width); $row < $rows; $row++) {
            $y = $top + $row;
            if ($y < $clip->y || $y >= $clip->bottom()) {
                continue;
            }
            for ($col = 0; $col < $width;) {
                $c = ord($coverage[$row * $width + $col]);
                $end = $col + strspn($coverage, $coverage[$row * $width + $col], $row * $width + $col, $width - $col);
                $x0 = max($left + $col, $clip->x);
                $x1 = min($left + $end, $clip->right());
                if ($c > 0 && $x1 > $x0) {
                    $spans .= Spans::pack($y, $x0, $x1 - $x0, $c);
                }
                $col = $end;
            }
        }
    }

    return $spans;
}

/** @return list<array{string, int, int, int}> overlapping masks of coverage runs, some past the surface's edges */
function randomMasks(Random\Randomizer $random, int $count, int $width, int $height): array
{
    $masks = [];
    for ($i = 0; $i < $count; $i++) {
        [$w, $h] = [$random->getInt(1, 9), $random->getInt(1, 7)];
        $coverage = '';
        while (strlen($coverage) < $w * $h) {
            $coverage .= str_repeat(chr([0, 0, 1, 127, 128, 254, 255, 255, $random->getInt(0, 255)][$random->getInt(0, 8)]), $random->getInt(1, 4));
        }
        $masks[] = [substr($coverage, 0, $w * $h), $w, $random->getInt(-4, $width), $random->getInt(-4, $height)];
    }

    return $masks;
}

function noisy(Framebuffer $buffer, int $seed): Framebuffer
{
    $random = new Random\Randomizer(new Random\Engine\Mt19937($seed));

    return $buffer->blitFrom(new Rgba8Source($random->getBytes($buffer->viewportWidth() * $buffer->viewportHeight() * 4), $buffer->viewportWidth(), $buffer->viewportHeight()));
}

it('paints masks as paintSpans paints their runs, with the same damage, on every kind', function (FramebufferDriver $driver, string $kind): void {
    $make = fn (): Framebuffer => match ($kind) {
        'full' => $driver->full(FormatSpec::rgba8(), 13, 11),
        'dirty' => $driver->dirty(FormatSpec::rgba8(), 13, 11),
        'paged' => $driver->paged(FormatSpec::rgba8(), 13, 11, 4),
        'ring' => $driver->ring(FormatSpec::rgba8(), 13, 11, 2),
    };
    [$masked, $spanned] = [noisy($make(), 5), noisy($make(), 5)];
    $random = new Random\Randomizer(new Random\Engine\Mt19937(6));

    foreach ([new Region(0, 0, 13, 11), new Region(2, 3, 7, 6), new Region(12, 0, 1, 11)] as $round => $clip) {
        $kind === 'paged' && $masked->setPage($round % 3) && $spanned->setPage($round % 3);
        $kind === 'dirty' && $masked->beginEpoch() && $spanned->beginEpoch();
        $masks = randomMasks($random, 12, 13, 11);
        $colour = $random->getInt(0, 0xFFFFFFFF);
        $masked->paintMasks($masks, $colour, $clip);
        $spans = masksAsSpans($masks, $clip);
        $spans === '' || $spanned->paintSpans($spans, $colour);

        expect(bin2hex($masked->dump()))->toBe(bin2hex($spanned->dump()), "round {$round}");
        $kind === 'dirty' && expect($masked->damage())->toEqual($spanned->damage(), "damage, round {$round}");
    }
})->with('framebuffer drivers')->with(['full', 'dirty', 'paged', 'ring']);

it('paints the same masks as the native store, in every format', function (FormatSpec $spec): void {
    [$native, $extended] = [noisy(new NativeFullFramebuffer($spec, 13, 9), 7), noisy(new ExtendedFullFramebuffer($spec, 13, 9), 7)];
    $random = new Random\Randomizer(new Random\Engine\Mt19937(8));

    for ($step = 0; $step < 20; $step++) {
        $masks = randomMasks($random, 6, 13, 9);
        $clip = new Region($random->getInt(0, 6), $random->getInt(0, 4), $random->getInt(1, 7), $random->getInt(1, 5));
        $colour = $random->getInt(0, 0xFFFFFFFF);
        $native->paintMasks($masks, $colour, $clip);
        $extended->paintMasks($masks, $colour, $clip);

        expect(bin2hex($extended->dump()))->toBe(bin2hex($native->dump()), "bytes after step {$step}");
    }
})->with(Formats::dataset())->skip(! method_exists(FbBuffer::class, 'paintMasks'), 'ext-fb with paintMasks() is not loaded in this PHP.');

it('refuses a bad clip, colour or mask before writing anything, with the same words on every driver', function (FramebufferDriver $driver, array $masks, int $colour, Region $clip, string $why): void {
    $buffer = $driver->dirty(Formats::rgb565(), 8, 4);
    $buffer->beginEpoch();

    expect(fn () => $buffer->paintMasks($masks, $colour, $clip))->toThrow(FramebufferException::class, $why)
        ->and(bin2hex($buffer->dump()))->toBe(str_repeat('00', 64))
        ->and($buffer->damage())->toBe([]);
})->with('framebuffer drivers')->with([
    'a clip past the width' => [[["\xFF", 1, 0, 0]], 0xFFFFFFFF, new Region(0, 0, 9, 1), 'The clip is empty or not inside a 8x4 framebuffer'],
    'an empty clip' => [[["\xFF", 1, 0, 0]], 0xFFFFFFFF, new Region(0, 0, 0, 1), 'The clip is empty or not inside a 8x4 framebuffer'],
    'a colour past 32 bits' => [[["\xFF", 1, 0, 0]], 0x100000000, new Region(0, 0, 1, 1), 'paintMasks() takes a colour 0xRRGGBBAA'],
    'a mask of three' => [[["\xFF", 1, 0, 0], ["\xFF", 1, 0]], 0xFFFFFFFF, new Region(0, 0, 1, 1), 'Mask 1 is not [string coverage, int width, int left, int top]'],
    'bytes that are not whole rows' => [[["\xFF\xFF\xFF", 2, 0, 0]], 0xFFFFFFFF, new Region(0, 0, 1, 1), 'Mask 0 is not whole rows'],
    'no bytes' => [[['', 1, 0, 0]], 0xFFFFFFFF, new Region(0, 0, 1, 1), 'Mask 0 is not whole rows'],
    'placed too far off' => [[["\xFF", 1, 1 << 31, 0]], 0xFFFFFFFF, new Region(0, 0, 1, 1), 'Mask 0 is placed past +/-2^30'],
]);

it('paints nothing for masks off the clip or of no coverage, and records no damage', function (FramebufferDriver $driver): void {
    $buffer = $driver->dirty(FormatSpec::rgba8(), 8, 8);
    $buffer->beginEpoch();
    $buffer->paintMasks([["\xFF\xFF", 2, 0, 0], [str_repeat("\0", 6), 3, 4, 4]], 0xFF0000FF, new Region(4, 4, 3, 3));

    expect($buffer->damage())->toBe([])
        ->and(bin2hex($buffer->dump()))->toBe(str_repeat('00', 256));
})->with('framebuffer drivers');
