<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Framebuffers\Spans;
use Surface\Framebuffers\Extended\ExtendedFullFramebuffer;
use Surface\Framebuffers\Extended\FbFormats;
use Surface\Framebuffers\Native\NativeFullFramebuffer;
use Surface\Framebuffers\PixelMapper;
use Venusian\Surface\Tests\Support\Framebuffers\Formats;
use Venusian\Surface\Tests\Support\Framebuffers\Rgba8Source;

/*
 * The two flavors are one design in two languages. Beyond the golden
 * fixtures, the same seeded stream of writes goes into a native and an
 * extended buffer of every format, and they must hold the same bytes after
 * every write and drain the same bytes into every other format.
 */

it('holds the same bytes as the native store after every write, in every format', function (FormatSpec $spec): void {
    $width = 13;
    $height = 9;
    $native = new NativeFullFramebuffer($spec, $width, $height);
    $extended = new ExtendedFullFramebuffer($spec, $width, $height);

    mt_srand(20261003);
    for ($step = 0; $step < 160; $step++) {
        $word = mt_rand(0, 1) ? mt_rand(0, 0xFFFFFFFF) : mt_rand(0, 7);
        $op = match (mt_rand(0, 6)) {
            0 => fn ($b) => $b->setPixel(mt_rand(0, $width - 1), mt_rand(0, $height - 1), $word),
            1 => fn ($b) => $b->setSegment(mt_rand(-3, $width), mt_rand(-3, $height), mt_rand(1, 9), mt_rand(1, 6), $word),
            2 => fn ($b) => $b->setPixels(array_map(fn () => [mt_rand(0, $width - 1), mt_rand(0, $height - 1), mt_rand(0, 0xFFFF)], range(1, 5))),
            3 => fn ($b) => $b->setRegion(array_map(fn () => [mt_rand(0, $width - 1), mt_rand(0, $height - 1)], range(1, 5)), $word),
            4 => fn ($b) => $b->blitFrom(new Rgba8Source(random_bytes_seeded(5 * 4 * 4), 5, 4), mt_rand(-4, $width), mt_rand(-3, $height)),
            5 => fn ($b) => mt_rand(0, 9) === 0 ? $b->fill($word) : $b->setPixel(0, 0, $word),
            6 => fn ($b) => mt_rand(0, 19) === 0 ? $b->clear() : $b->setPixel($width - 1, $height - 1, $word),
        };

        // the same random draws for both buffers
        $seed = mt_rand();
        mt_srand($seed);
        $op($native);
        mt_srand($seed);
        $op($extended);

        expect(bin2hex($extended->dump()))->toBe(bin2hex($native->dump()), "bytes after step {$step}");
    }

    expect(bin2hex($extended->toRgba8()))->toBe(bin2hex($native->toRgba8()));
    expect(bin2hex($extended->store()->plane(1)))->toBe(bin2hex($native->store()->plane(1)));
    for ($x = 0; $x < $width; $x++) {
        expect($extended->getPixel($x, $x % $height))->toBe($native->getPixel($x, $x % $height));
    }

    foreach (Formats::all() as $name => $target) {
        expect(bin2hex($extended->flush($target)))->toBe(bin2hex($native->flush($target)), "flush to {$name}");
        $region = new Region(3, 2, 7, 5);
        expect(bin2hex($extended->flushRegion($region, $target)))->toBe(bin2hex($native->flushRegion($region, $target)), "region to {$name}");
    }
})->with(Formats::dataset())->skip(! class_exists(FbBuffer::class), 'ext-fb 0.10 is not loaded in this PHP.');

it('paints the same span bytes as the native store, in every format', function (FormatSpec $spec): void {
    $width = 13;
    $height = 9;
    $native = new NativeFullFramebuffer($spec, $width, $height);
    $extended = new ExtendedFullFramebuffer($spec, $width, $height);
    $random = new Random\Randomizer(new Random\Engine\Mt19937(20261004));
    $noise = $random->getBytes($width * $height * 4);
    $native->blitFrom(new Rgba8Source($noise, $width, $height));
    $extended->blitFrom(new Rgba8Source($noise, $width, $height));

    for ($step = 0; $step < 60; $step++) {
        $spans = '';
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width;) {
                $length = $random->getInt(1, $width - $x);
                if ($random->getInt(0, 2) > 0) {
                    $spans .= Spans::pack($y, $x, $length, $random->getInt(0, 3) === 0 ? 255 : $random->getInt(1, 255));
                }
                $x += $length;
            }
        }
        $colour = $random->getInt(0, 0xFFFFFFFF);

        $native->paintSpans($spans, $colour);
        $extended->paintSpans($spans, $colour);
        expect(bin2hex($extended->dump()))->toBe(bin2hex($native->dump()), "bytes after step {$step}");
    }
})->with(Formats::dataset())->skip(! class_exists(FbBuffer::class), 'ext-fb 0.10 is not loaded in this PHP.');

it('maps every colour to the same word, and every word to the same colour, as the native mapper', function (FormatSpec $spec): void {
    $mapper = PixelMapper::for($spec);
    $format = FbFormats::from($spec);

    foreach (range(0, 255, 15) as $red) {
        foreach (range(0, 255, 17) as $green) {
            foreach ([0, 1, 127, 128, 254, 255] as $blue) {
                $alpha = ($red + $green) % 256;
                expect($format->mapRgba8($red, $green, $blue, $alpha))->toBe($mapper->fromRgba8($red, $green, $blue, $alpha), "({$red}, {$green}, {$blue}, {$alpha})");
            }
        }
    }

    mt_srand(7);
    foreach ([...range(0, 4096), ...array_map(fn () => mt_rand(0, 0xFFFFFFFF), range(1, 2000))] as $word) {
        expect($format->unmapRgba8($word))->toBe($mapper->toRgba8($word), "word {$word}");
    }
})->with(Formats::dataset())->skip(! class_exists(FbBuffer::class), 'ext-fb 0.10 is not loaded in this PHP.');

/** Bytes from mt_rand(), so a seeded run draws the same pixels twice. */
function random_bytes_seeded(int $length): string
{
    return implode('', array_map(fn (): string => chr(mt_rand(0, 255)), range(1, $length)));
}
