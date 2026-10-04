<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Images\ImageDecoder;
use Surface\Contracts\Images\ImageException;
use Venusian\Surface\Tests\Support\Images\Pixels;
use Venusian\Surface\Tests\Support\Images\PngWriter;

/*
 * Each PNG is written sample by sample, so the expected RGBA8 is worked from
 * the PNG rules in ImageDecoder: palettes and grey expanded, tRNS made alpha,
 * 16-bit cut to the high byte. The native driver's alpha goes through gd's
 * 7 bits (Pixels::asPng).
 */

function decodedPng(ImageDecoder $decoder, string $png): string
{
    $image = $decoder->decode($png);

    expect($image->hostFormat())->toEqual(FormatSpec::rgba8());

    return bin2hex($image->toRgba8());
}

function greyPixels(array $greys, ?array $alphas = null): array
{
    return array_map(fn (int $g, int $i): array => [$g, $g, $g, $alphas[$i] ?? 255], $greys, array_keys($greys));
}

it('reads RGBA', function (ImageDecoder $decoder): void {
    $png = PngWriter::write(5, 3, 6, 8, Pixels::samples(Pixels::BASE, 4));

    expect(decodedPng($decoder, $png))->toBe(bin2hex(Pixels::asPng($decoder->driver(), Pixels::rgba(Pixels::BASE))));
})->with('image decoders');

it('reads RGB as opaque', function (ImageDecoder $decoder): void {
    $png = PngWriter::write(5, 3, 2, 8, Pixels::samples(Pixels::BASE, 3));

    expect(decodedPng($decoder, $png))->toBe(bin2hex(Pixels::rgba(Pixels::opaque(Pixels::BASE))));
})->with('image decoders');

it('makes the one RGB colour tRNS names transparent', function (ImageDecoder $decoder): void {
    $png = PngWriter::write(5, 3, 2, 8, Pixels::samples(Pixels::BASE, 3), trns: [18, 52, 86]);
    $expected = Pixels::opaque(Pixels::BASE);
    $expected[10][3] = 0;

    expect(decodedPng($decoder, $png))->toBe(bin2hex(Pixels::rgba($expected)));
})->with('image decoders');

it('cuts 16-bit samples to their high byte', function (ImageDecoder $decoder): void {
    $samples = array_map(fn (int $v): int => ($v << 8) | 0xA7, Pixels::samples(Pixels::BASE, 4));
    $png = PngWriter::write(5, 3, 6, 16, $samples);

    expect(decodedPng($decoder, $png))->toBe(bin2hex(Pixels::asPng($decoder->driver(), Pixels::rgba(Pixels::BASE))));
})->with('image decoders');

it('reads grey as opaque RGB', function (ImageDecoder $decoder): void {
    $png = PngWriter::write(5, 3, 0, 8, Pixels::GREY);

    expect(decodedPng($decoder, $png))->toBe(bin2hex(Pixels::rgba(greyPixels(Pixels::GREY))));
})->with('image decoders');

it('reads grey with alpha', function (ImageDecoder $decoder): void {
    $samples = array_merge(...array_map(null, Pixels::GREY, Pixels::GREY_ALPHA));
    $png = PngWriter::write(5, 3, 4, 8, $samples);

    expect(decodedPng($decoder, $png))->toBe(bin2hex(Pixels::asPng($decoder->driver(), Pixels::rgba(greyPixels(Pixels::GREY, Pixels::GREY_ALPHA)))));
})->with('image decoders');

it('widens 1, 2 and 4-bit grey across 0..255', function (ImageDecoder $decoder, int $bits, array $values, array $greys): void {
    $png = PngWriter::write(count($values), 1, 0, $bits, $values);

    expect(decodedPng($decoder, $png))->toBe(bin2hex(Pixels::rgba(greyPixels($greys))));
})->with('image decoders')->with([
    '1-bit' => [1, [0, 1, 1, 0, 1, 0, 0, 1, 1], [0, 255, 255, 0, 255, 0, 0, 255, 255]],
    '2-bit' => [2, [0, 1, 2, 3, 3], [0, 85, 170, 255, 255]],
    '4-bit' => [4, [0, 1, 7, 14, 15], [0, 17, 119, 238, 255]],
]);

it('makes the one grey tRNS names transparent', function (ImageDecoder $decoder): void {
    $png = PngWriter::write(5, 3, 0, 8, Pixels::GREY, trns: [128]);
    $alphas = array_map(fn (int $g): int => $g === 128 ? 0 : 255, Pixels::GREY);

    expect(decodedPng($decoder, $png))->toBe(bin2hex(Pixels::rgba(greyPixels(Pixels::GREY, $alphas))));
})->with('image decoders');

it('expands a palette and its tRNS alphas', function (ImageDecoder $decoder): void {
    $palette = array_map(fn (array $p): array => array_slice($p, 0, 3), Pixels::BASE);
    $png = PngWriter::write(5, 3, 3, 8, range(0, 14), $palette, array_column(Pixels::BASE, 3));

    expect(decodedPng($decoder, $png))->toBe(bin2hex(Pixels::asPng($decoder->driver(), Pixels::rgba(Pixels::BASE))));
})->with('image decoders');

it('leaves palette entries past a short tRNS opaque', function (ImageDecoder $decoder): void {
    $png = PngWriter::write(4, 1, 3, 4, [0, 1, 2, 3], [[10, 20, 30], [40, 50, 60], [70, 80, 90], [100, 110, 120]], [0, 128]);
    $expected = [[10, 20, 30, 0], [40, 50, 60, 128], [70, 80, 90, 255], [100, 110, 120, 255]];

    expect(decodedPng($decoder, $png))->toBe(bin2hex(Pixels::asPng($decoder->driver(), Pixels::rgba($expected))));
})->with('image decoders');

it('undoes Adam7 interlacing', function (ImageDecoder $decoder): void {
    $pixels = [];
    for ($y = 0; $y < 11; $y++) {
        for ($x = 0; $x < 13; $x++) {
            $pixels[] = [$x * 19 % 256, $y * 23 % 256, ($x * $y) % 256, ($x + $y) % 2 ? 255 : 0];
        }
    }
    $png = PngWriter::write(13, 11, 6, 8, Pixels::samples($pixels, 4), interlaced: true);

    expect(decodedPng($decoder, $png))->toBe(bin2hex(Pixels::rgba($pixels)));
})->with('image decoders');

it('refuses a cut-off PNG', function (ImageDecoder $decoder): void {
    $png = PngWriter::write(5, 3, 6, 8, Pixels::samples(Pixels::BASE, 4));

    expect(fn () => $decoder->decode(substr($png, 0, 60)))->toThrow(ImageException::class, 'PNG image could not be read');
})->with('image decoders');

it('refuses a PNG past the pixel limit before decoding it', function (ImageDecoder $decoder): void {
    $png = PngWriter::write(8193, 8192, 0, 1, []);

    expect(fn () => $decoder->decode($png))->toThrow(ImageException::class, 'A 8193x8192 image is past the limit: sides up to 65535, 67108864 pixels in all.');
})->with('image decoders');
