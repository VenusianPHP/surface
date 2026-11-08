<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\Effects\Blend;
use Surface\Contracts\Drawing\Effects\BlendMode;
use Surface\Contracts\Drawing\Effects\Blur;
use Surface\Contracts\Drawing\Effects\ColorMatrix;
use Surface\Contracts\Drawing\Effects\Composite;
use Surface\Contracts\Drawing\Effects\CompositeOperator;
use Surface\Contracts\Drawing\Effects\Displace;
use Surface\Contracts\Drawing\Effects\EdgeMode;
use Surface\Contracts\Drawing\Effects\FilterChain;
use Surface\Contracts\Drawing\Effects\Field;
use Surface\Contracts\Drawing\Effects\Flood;
use Surface\Contracts\Drawing\Effects\Table;
use Surface\Contracts\Drawing\Effects\Noise;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Framebuffers\Region;
use Surface\Framebuffers\Native\NativeFilters;
use Surface\NutsAndBolts\Color;
use Venusian\Surface\Tests\Support\Framebuffers\Rgba8Source;

/*
 * Framebuffer::filter() on every framebuffer driver: the bytes are those of NativeFilters, the reference. The
 * reference itself is held to values worked out by hand and to properties a filter has (a flat image stays flat, noise is a
 * function of the surface's position).
 */

/** A w x h image of varied, partly see-through pixels. */
function filterImage(int $w, int $h): string
{
    $bytes = '';
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $bytes .= chr(($x * 37 + $y * 11) % 256).chr(($x * 5 + $y * 71 + 40) % 256).chr(($x * 13 + $y * 29 + 90) % 256).chr(($x + $y) % 3 === 0 ? 128 : 255);
        }
    }

    return $bytes;
}

function filterMap(int $w, int $h): Framebuffer
{
    $bytes = '';
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $bytes .= chr(($x * 23 + 60) % 256).chr(($y * 31 + 90) % 256).chr(128)."\xff";
        }
    }

    return new Rgba8Source($bytes, $w, $h);
}

function filteredOn(FramebufferDriver $driver, FilterChain $chain, int $w, int $h, ?Region $region = null): string
{
    $buffer = $driver->full(FormatSpec::rgba8(), $w, $h);
    $buffer->writeRgba8(filterImage($w, $h), $w, $h);
    $buffer->filter($chain, $region);

    return $buffer->dump();
}

function filterChains(): array
{
    return [
        'gaussian below 2' => FilterChain::of(Blur::gaussian(1.2, 0.6, EdgeMode::DUPLICATE)),
        'gaussian, odd boxes' => FilterChain::of(Blur::gaussian(2.5, 2.5)),
        'gaussian, even boxes, one axis' => FilterChain::of(Blur::gaussian(2.2, 0.0, EdgeMode::DUPLICATE)),
        'spiral' => FilterChain::of(Blur::spiral(3.5, 16)),
        'spiral, edges held' => FilterChain::of(Blur::spiral(5.0, 7, EdgeMode::DUPLICATE)),
        'colour matrix' => FilterChain::of(ColorMatrix::saturate(0.3), ColorMatrix::hueRotate(40.0), ColorMatrix::brightness(0.8, 0.1)),
        'luminance to alpha' => FilterChain::of(ColorMatrix::luminanceToAlpha()),
        'flood and composite' => FilterChain::of(new Flood(Color::rgba(200, 40, 90, 0.6), 'f'), new Composite('source', CompositeOperator::ATOP, in: 'f')),
        'every composite' => FilterChain::of(new Flood(Color::rgba(10, 200, 90, 0.5), 'f'), new Composite('f', CompositeOperator::OVER, result: 'a'), new Composite('f', CompositeOperator::IN, in: 'source', result: 'b'), new Composite('f', CompositeOperator::OUT, in: 'source', result: 'c'), new Composite('f', CompositeOperator::XOR, in: 'source', result: 'd'), Composite::arithmetic('c', 0.3, 0.5, 0.4, 0.05, in: 'd')),
        'every blend' => FilterChain::of(new Flood(Color::rgba(90, 120, 200, 0.7), 'f'), new Blend('f', BlendMode::MULTIPLY, result: 'm'), new Blend('f', BlendMode::SCREEN, in: 'm', result: 's'), new Blend('f', BlendMode::DARKEN, in: 's', result: 'd'), new Blend('f', BlendMode::LIGHTEN, in: 'd')),
        'turbulence' => FilterChain::of(Noise::turbulence(0.07, 0.11, 3, 5)),
        'fractal, stitched' => FilterChain::of(Noise::fractal(0.05, 0.05, 2, 12, true)),
        'hash noise' => FilterChain::of(Noise::hash(9)),
        'displacement' => FilterChain::of(new Displace(filterMap(24, 16), 6.5)),
        'dispersion' => FilterChain::of(new Displace(filterMap(24, 16), 9.0, bands: 7, spread: 0.35)),
        'field of a rounded box' => FilterChain::of(Field::roundedBox([3.2, 2.7, 20.4, 13.1], [5.0, 3.0, 6.5, 1.0], -4.0, 0.0)),
        'field inside a box' => FilterChain::of(Field::inside([3.0, 2.0, 21.0, 14.0], [4.0, 4.0, 4.0, 4.0])),
        'field ramp' => FilterChain::of(Field::ramp(2.0, 14.0)),
        'table on a field' => FilterChain::of(Field::roundedBox([3.2, 2.7, 20.4, 13.1], [5.0, 3.0, 6.5, 1.0], -4.0, 0.0), Table::rgb(fn (float $v): float => $v ** 3)),
        'table on the source, with an alpha table' => FilterChain::of(new Table(array_map(fn ($i) => 255 - $i, range(0, 255)), null, array_map(fn ($i) => intdiv($i, 2), range(0, 255)), array_map(fn ($i) => max($i, 40), range(0, 255)))),
        'displacement by a map a field makes' => FilterChain::of(Field::roundedBox([2.0, 2.0, 22.0, 14.0], [4.0, 4.0, 4.0, 4.0], -6.0, 0.0, 'm'), new Displace('m', 7.0, in: 'source', bands: 3, spread: 0.2)),
        'blur, noise and displacement named' => FilterChain::of(Blur::gaussian(3.0, 3.0, result: 'b'), Noise::turbulence(0.1, 0.1, 2, 3, result: 'n'), new Displace(filterMap(24, 16), 5.0, in: 'b'), new Composite('n', CompositeOperator::ARITHMETIC, 0.0, 0.8, 0.2, 0.0)),
    ];
}

it('gives the bytes of the reference, whole surface and a region', function (FramebufferDriver $driver, string $name): void {
    $chain = filterChains()[$name];
    $whole = filteredOn($driver, $chain, 24, 16);
    expect($whole)->toBe(NativeFilters::run($chain, filterImage(24, 16), 24, 16));

    // a region reads and writes only itself; noise is a function of the position on the surface
    $region = new Region(5, 3, 11, 9);
    $part = filteredOn($driver, $chain, 24, 16, $region);
    $image = filterImage(24, 16);
    $cut = '';
    for ($y = 3; $y < 12; $y++) {
        $cut .= substr($image, ($y * 24 + 5) * 4, 11 * 4);
    }
    $filtered = NativeFilters::run($chain, $cut, 11, 9, 5, 3);
    for ($y = 0; $y < 16; $y++) {
        for ($x = 0; $x < 24; $x++) {
            $inside = $region->x <= $x && $x < $region->right() && $region->y <= $y && $y < $region->bottom();
            $want = $inside ? substr($filtered, (($y - 3) * 11 + $x - 5) * 4, 4) : substr($image, ($y * 24 + $x) * 4, 4);
            expect(substr($part, ($y * 24 + $x) * 4, 4))->toBe($want);
        }
    }
})->with('framebuffer drivers')->with(array_keys(filterChains()));

it('keeps a flat image flat under a blur with held edges', function (): void {
    $flat = str_repeat("\x40\x80\xc0\xff", 12 * 9);
    foreach ([Blur::gaussian(1.5, null, EdgeMode::DUPLICATE), Blur::gaussian(3.0, 3.0, EdgeMode::DUPLICATE), Blur::spiral(4.0, 16, EdgeMode::DUPLICATE)] as $blur) {
        expect(NativeFilters::run(FilterChain::of($blur), $flat, 12, 9))->toBe($flat);
    }
});

it('blurs a point into a symmetric blob', function (): void {
    $image = str_repeat("\0\0\0\0", 21 * 21);
    $at = (10 * 21 + 10) * 4;
    $image = substr_replace($image, "\xff\xff\xff\xff", $at, 4);
    $out = NativeFilters::run(FilterChain::of(Blur::gaussian(2.5, 2.5)), $image, 21, 21);
    $alpha = fn (int $x, int $y): int => ord($out[($y * 21 + $x) * 4 + 3]);

    expect($alpha(10, 10))->toBeGreaterThan($alpha(9, 10))
        ->and($alpha(9, 10))->toBe($alpha(11, 10))
        ->and($alpha(10, 9))->toBe($alpha(10, 11))
        ->and(abs($alpha(9, 10) - $alpha(10, 9)))->toBeLessThanOrEqual(1);   // the pass across rounds once more than the pass along
});

it('floods, composites and blends with values worked out by hand', function (): void {
    $pixel = "\x80\x00\x00\x80";   // straight (128, 0, 0) at alpha 128: premultiplied (64, 0, 0, 128)
    // flood (0, 255, 0) at alpha 255 over it: 'over' puts the flood (the first input, `in`) on top: opaque green
    $over = FilterChain::of(new Flood(Color::rgb(0, 255, 0), 'f'), new Composite('source', CompositeOperator::OVER, in: 'f'));
    // 'in': the flood kept where the source has alpha 128/255, premultiplied 0, 128, 0, 128: straight (0, 255, 0, 128)
    $in = FilterChain::of(new Flood(Color::rgb(0, 255, 0), 'f'), new Composite('source', CompositeOperator::IN, in: 'f'));

    expect(bin2hex(NativeFilters::run($over, $pixel, 1, 1)))->toBe('00ff00ff')
        ->and(bin2hex(NativeFilters::run($in, $pixel, 1, 1)))->toBe('00ff0080');
    // multiply (flood below, the source on top): (1 - qa) cb + (1 - qb) ca + ca cb with qb = 1 gives ca × cb = 0 on a green flood and red source
    $multiply = FilterChain::of(new Flood(Color::rgb(0, 255, 0), 'f'), new Blend('f', BlendMode::MULTIPLY, in: 'source'));
    // premultiplied: red ca = 64/255, alpha qa = 128/255; below cb = (0, 1, 0), qb = 1: r = (1 - qa)·0 + 0·ca + ca·0 = 0; g = (1-qa)·1 + 0 + 0 = 127/255 ≈ 127; alpha 255
    expect(bin2hex(NativeFilters::run($multiply, $pixel, 1, 1)))->toBe('007f00ff');
});

it('displaces by nothing when the map is mid-grey', function (): void {
    $image = preg_replace('/(...)./s', "$1\xff", filterImage(8, 6));   // opaque: a see-through pixel does not survive premultiplying and back
    $grey = new Rgba8Source(str_repeat("\x80\x80\x80\xff", 8 * 6), 8, 6);
    // 128/255 - 0.5 is a hair above zero: scale 1 moves the sample by 0.0019 pixels, which a 1/256 weight rounds away
    expect(NativeFilters::run(FilterChain::of(new Displace($grey, 1.0)), $image, 8, 6))->toBe($image);
});

it('makes hash noise a function of the surface position and the seed', function (): void {
    $chain = FilterChain::of(Noise::hash(4));
    $whole = NativeFilters::run($chain, str_repeat("\0", 6 * 4 * 4), 6, 4);
    $part = NativeFilters::run($chain, str_repeat("\0", 2 * 2 * 4), 2, 2, 3, 1);

    expect(substr($part, 0, 4))->toBe(substr($whole, (1 * 6 + 3) * 4, 4))
        ->and(substr($part, 8, 4))->toBe(substr($whole, (2 * 6 + 3) * 4, 4))
        ->and(NativeFilters::run(FilterChain::of(Noise::hash(5)), str_repeat("\0", 6 * 4 * 4), 6, 4))->not->toBe($whole)
        ->and(ord($whole[3]))->toBe(255);
});

it('has turbulence nothing at the lattice points', function (): void {
    // noise2 is 0 where both coordinates are whole: frequency 1 samples whole numbers
    $out = NativeFilters::run(FilterChain::of(Noise::turbulence(1.0, 1.0, 1, 7)), str_repeat("\0", 4 * 4 * 4), 4, 4);

    expect($out)->toBe(str_repeat("\0", 4 * 4 * 4));
});

it('takes premultiplied pixels as they are and writes them straight, with a chain of no steps too', function (FramebufferDriver $driver): void {
    // premultiplied (64, 0, 0) at alpha 128 is straight (127.5, 0, 0) = (128, 0, 0): (64 × 255 + 64) / 128 = 128
    $buffer = $driver->full(FormatSpec::rgba8(), 2, 1);
    $buffer->writeRgba8("\x40\x00\x00\x80\x00\x00\x00\x00", 2, 1);
    $buffer->filter(FilterChain::of(), null, true);

    expect(bin2hex($buffer->dump()))->toBe('80000080'.'00000000');

    // with a step: the flood under the source ('over' with the source on top), premultiplied arithmetic throughout
    $buffer = $driver->full(FormatSpec::rgba8(), 1, 1);
    $buffer->writeRgba8("\x40\x00\x00\x80", 1, 1);
    $buffer->filter(FilterChain::of(new Flood(Color::rgb(0, 0, 255), 'f'), new Composite('f', CompositeOperator::OVER, in: 'source')), null, true);
    // r = 64 + 0 × (1 - 128/255) = 64; b = 0 + 255 × (127/255) = 127; alpha 255: straight the same
    expect(bin2hex($buffer->dump()))->toBe('4000'.'7f'.'ff');
})->with('framebuffer drivers');

it('gives an opaque image back from an empty chain', function (): void {
    $opaque = preg_replace('/(...)./s', "$1\xff", filterImage(5, 3));

    expect(NativeFilters::run(FilterChain::of(), $opaque, 5, 3))->toBe($opaque);
});

it('writes the distance, the direction and the mask of a rounded box, with values worked out by hand', function (): void {
    // a 10 × 6 box at (1, 1): the pixel (5, 3) has centre (5.5, 3.5), the box centre, 3 from the top and 3 from the bottom
    $chain = FilterChain::of(Field::roundedBox([1.0, 1.0, 11.0, 7.0], [0.0, 0.0, 0.0, 0.0], -6.0, 0.0));
    $out = NativeFilters::run($chain, str_repeat("\0\0\0\0", 12 * 8), 12, 8);
    $pixel = fn (int $x, int $y): array => array_values(unpack('C4', substr($out, ($y * 12 + $x) * 4, 4)));
    // d = −3 at the centre: (−3 + 6) / 6 = ½ → 128; no direction either way is ambiguous at the centre, so check a pixel off it
    // (2, 3): centre (2.5, 3.5): 1.5 in from the left edge: d = −1.5 → (4.5 / 6 = 0.75 → 191); the direction is left: nx = −1 → 0, ny = 0 → 128
    expect($pixel(2, 3))->toBe([191, 0, 128, 255])
        // (0, 0): outside, 0.5 from the left and top edges, a corner: d = hypot(0.5, 0.5) = 0.7071 → (6.7071 / 6 ≥ 1) 255
        ->and($pixel(0, 0)[0])->toBe(255);

    $inside = NativeFilters::run(FilterChain::of(Field::inside([1.0, 1.0, 11.0, 7.0], [0.0, 0.0, 0.0, 0.0])), str_repeat("\0\0\0\0", 12 * 8), 12, 8);
    expect(substr($inside, (3 * 12 + 2) * 4, 4))->toBe("\xff\xff\xff\xff")->and(substr($inside, 0, 4))->toBe("\0\0\0\xff");

    $ramp = NativeFilters::run(FilterChain::of(Field::ramp(0.0, 8.0)), str_repeat("\0\0\0\0", 12 * 8), 12, 8, 0, 4);
    // the surface row 4 + 0 has its centre at 4.5: 4.5 / 8 = 0.5625 → 143
    expect(ord($ramp[0]))->toBe(143);
});

it('inverts the red of an opaque pixel and leaves green and blue', function (): void {
    $invert = array_map(fn ($i) => 255 - $i, range(0, 255));
    $out = NativeFilters::run(FilterChain::of(new Table($invert)), "\x10\x20\x30\xff", 1, 1);

    expect($out)->toBe("\xef\x20\x30\xff");
});

it('refuses what a GPU could not do within its limits, the same on every path', function (): void {
    expect(fn () => Blur::gaussian(Blur::MAX_DEVIATION + 1))->toThrow(Surface\Contracts\Drawing\DrawingException::class)
        ->and(fn () => Blur::gaussian(Blur::MAX_DEVIATION))->not->toThrow(Surface\Contracts\Drawing\DrawingException::class)
        ->and(fn () => Blur::spiral(500.0, Blur::MAX_SAMPLES))->not->toThrow(Surface\Contracts\Drawing\DrawingException::class)
        ->and(fn () => Noise::turbulence(0.1, 0.1, Noise::MAX_OCTAVES + 1))->toThrow(Surface\Contracts\Drawing\DrawingException::class)
        ->and(fn () => Field::ramp(1.0, 1.0))->toThrow(Surface\Contracts\Drawing\DrawingException::class)
        ->and(fn () => Field::ramp(0.0, 1e-31))->toThrow(Surface\Contracts\Drawing\DrawingException::class)
        ->and(fn () => Field::ramp(0.0, 1e-6))->not->toThrow(Surface\Contracts\Drawing\DrawingException::class);
});
