<?php

declare(strict_types=1);

use Surface\Contracts\Images\ImageDecoder;
use Surface\Contracts\Images\ImageException;
use Venusian\Surface\Tests\Support\Images\Pixels;
use Venusian\Surface\Tests\Support\Images\TiffWriter;

/*
 * Each TIFF is written sample by sample, so the expected RGBA8 is worked from
 * the TIFF rules in ImageDecoder. Both drivers answer the same bytes here:
 * TIFF never goes through gd.
 */

function decodedTiff(ImageDecoder $decoder, string $tiff): string
{
    return bin2hex($decoder->decode($tiff)->toRgba8());
}

/** A seeded, compressible-but-not-trivial picture: noise over a gradient. */
function noisyPixels(int $width, int $height, int $seed): array
{
    mt_srand($seed);
    $pixels = [];
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $pixels[] = [($x * 7 + mt_rand(0, 3)) % 256, ($y * 5 + mt_rand(0, 40)) % 256, mt_rand(0, 255), mt_rand(0, 1) ? 255 : mt_rand(0, 255)];
        }
    }

    return $pixels;
}

it('reads RGB in either byte order', function (ImageDecoder $decoder, bool $big): void {
    $tiff = TiffWriter::write(5, 3, 2, 8, 3, Pixels::samples(Pixels::BASE, 3), ['big_endian' => $big]);

    expect(decodedTiff($decoder, $tiff))->toBe(bin2hex(Pixels::rgba(Pixels::opaque(Pixels::BASE))));
})->with('image decoders')->with(['little-endian' => false, 'big-endian' => true]);

it('keeps unassociated alpha as it is', function (ImageDecoder $decoder): void {
    $tiff = TiffWriter::write(5, 3, 2, 8, 4, Pixels::samples(Pixels::BASE, 4), ['extra_samples' => [2]]);

    expect(decodedTiff($decoder, $tiff))->toBe(bin2hex(Pixels::rgba(Pixels::BASE)));
})->with('image decoders');

it('divides associated alpha out', function (ImageDecoder $decoder): void {
    $premultiplied = array_map(fn (array $p): array => [
        intdiv($p[0] * $p[3] + 127, 255), intdiv($p[1] * $p[3] + 127, 255), intdiv($p[2] * $p[3] + 127, 255), $p[3],
    ], Pixels::BASE);
    $straight = array_map(fn (array $p): array => $p[3] === 0 ? [0, 0, 0, 0] : [
        min(255, intdiv($p[0] * 255 + ($p[3] >> 1), $p[3])), min(255, intdiv($p[1] * 255 + ($p[3] >> 1), $p[3])), min(255, intdiv($p[2] * 255 + ($p[3] >> 1), $p[3])), $p[3],
    ], $premultiplied);
    $tiff = TiffWriter::write(5, 3, 2, 8, 4, Pixels::samples($premultiplied, 4), ['extra_samples' => [1]]);

    expect(decodedTiff($decoder, $tiff))->toBe(bin2hex(Pixels::rgba($straight)))
        ->and($straight[5])->toBe([0, 0, 0, 0]);
})->with('image decoders');

it('ignores an extra sample that is not alpha', function (ImageDecoder $decoder): void {
    $tiff = TiffWriter::write(5, 3, 2, 8, 4, Pixels::samples(Pixels::BASE, 4), ['extra_samples' => [0]]);

    expect(decodedTiff($decoder, $tiff))->toBe(bin2hex(Pixels::rgba(Pixels::opaque(Pixels::BASE))));
})->with('image decoders');

it('reads grey with black or white as zero', function (ImageDecoder $decoder, int $photometric, Closure $shade): void {
    $tiff = TiffWriter::write(5, 3, $photometric, 8, 1, Pixels::GREY);
    $greys = array_map($shade, Pixels::GREY);

    expect(decodedTiff($decoder, $tiff))->toBe(bin2hex(Pixels::rgba(array_map(fn (int $g): array => [$g, $g, $g, 255], $greys))));
})->with('image decoders')->with([
    'min-is-black' => [1, fn (int $g): int => $g],
    'min-is-white' => [0, fn (int $g): int => 255 - $g],
]);

it('widens 1, 2 and 4-bit grey, rows starting on a byte', function (ImageDecoder $decoder, int $photometric, int $bits, array $values, array $greys): void {
    $tiff = TiffWriter::write(count($values), 2, $photometric, $bits, 1, [...$values, ...$values]);
    $row = array_map(fn (int $g): array => [$g, $g, $g, 255], $greys);

    expect(decodedTiff($decoder, $tiff))->toBe(bin2hex(Pixels::rgba([...$row, ...$row])));
})->with('image decoders')->with([
    '1-bit black zero' => [1, 1, [0, 1, 1, 0, 1, 0, 0, 1, 1], [0, 255, 255, 0, 255, 0, 0, 255, 255]],
    '1-bit white zero' => [0, 1, [0, 1, 1], [255, 0, 0]],
    '2-bit' => [1, 2, [0, 1, 2, 3, 3], [0, 85, 170, 255, 255]],
    '4-bit' => [1, 4, [0, 1, 7, 14, 15], [0, 17, 119, 238, 255]],
]);

it('reads grey with alpha', function (ImageDecoder $decoder): void {
    $samples = array_merge(...array_map(null, Pixels::GREY, Pixels::GREY_ALPHA));
    $tiff = TiffWriter::write(5, 3, 1, 8, 2, $samples, ['extra_samples' => [2]]);
    $expected = array_map(fn (int $g, int $a): array => [$g, $g, $g, $a], Pixels::GREY, Pixels::GREY_ALPHA);

    expect(decodedTiff($decoder, $tiff))->toBe(bin2hex(Pixels::rgba($expected)));
})->with('image decoders');

it('cuts 16-bit samples to their high byte in either byte order', function (ImageDecoder $decoder, bool $big): void {
    $samples = array_map(fn (int $v): int => ($v << 8) | 0x5C, Pixels::samples(Pixels::BASE, 3));
    $tiff = TiffWriter::write(5, 3, 2, 16, 3, $samples, ['big_endian' => $big]);

    expect(decodedTiff($decoder, $tiff))->toBe(bin2hex(Pixels::rgba(Pixels::opaque(Pixels::BASE))));
})->with('image decoders')->with(['little-endian' => false, 'big-endian' => true]);

it('looks a palette up, taking each 16-bit colour\'s high byte', function (ImageDecoder $decoder, int $bits): void {
    $colormap = array_map(fn (int $i): array => [($i * 17) << 8 | 0xFF, (255 - $i * 9) << 8, ($i * 3) << 8 | 0x10], range(0, (1 << $bits) - 1));
    $indices = [0, 3, 15, 2, 9, 1, 14, 7, 0, 5, 11, 4, 13, 6, 12];
    $tiff = TiffWriter::write(5, 3, 3, $bits, 1, $indices, ['colormap' => $colormap]);
    $expected = array_map(fn (int $i): array => [$i * 17, 255 - $i * 9, $i * 3, 255], $indices);

    expect(decodedTiff($decoder, $tiff))->toBe(bin2hex(Pixels::rgba($expected)));
})->with('image decoders')->with(['4-bit' => 4, '8-bit' => 8]);

it('decompresses PackBits, LZW and Deflate, with or without the horizontal predictor', function (ImageDecoder $decoder, string $compression, int $predictor, int $bits): void {
    $pixels = noisyPixels(61, 37, 7);
    $samples = array_map(fn (int $v): int => $bits === 16 ? ($v << 8) | 0x33 : $v, Pixels::samples($pixels, 4));
    $tiff = TiffWriter::write(61, 37, 2, $bits, 4, $samples, ['extra_samples' => [2], 'compression' => $compression, 'predictor' => $predictor, 'rows_per_strip' => 8]);

    expect(decodedTiff($decoder, $tiff))->toBe(bin2hex(Pixels::rgba($pixels)));
})->with('image decoders')->with([
    'PackBits' => ['packbits', 1, 8],
    'LZW' => ['lzw', 1, 8],
    'LZW + predictor' => ['lzw', 2, 8],
    'Deflate' => ['deflate', 1, 8],
    'Deflate + predictor, 16-bit' => ['deflate', 2, 16],
]);

it('interleaves separate planes, the way GIBS WMS writes its TIFFs', function (ImageDecoder $decoder, array $options, int $bits): void {
    $pixels = noisyPixels(23, 17, 5);
    $samples = array_map(fn (int $v): int => $bits === 16 ? ($v << 8) | 0x71 : $v, Pixels::samples($pixels, 4));
    $tiff = TiffWriter::write(23, 17, 2, $bits, 4, $samples, ['extra_samples' => [2], 'planar' => 2] + $options);

    expect(decodedTiff($decoder, $tiff))->toBe(bin2hex(Pixels::rgba($pixels)));
})->with('image decoders')->with([
    'strips' => [['rows_per_strip' => 11], 8],
    'strips, LZW + predictor, 16-bit, big-endian' => [['rows_per_strip' => 4, 'compression' => 'lzw', 'predictor' => 2, 'big_endian' => true], 16],
    'tiles' => [['tile' => [16, 16], 'compression' => 'deflate'], 8],
]);

it('leaves samples as they are when a predictor tag sits on a codec without one', function (ImageDecoder $decoder, string $compression): void {
    $tiff = TiffWriter::write(5, 3, 2, 8, 3, Pixels::samples(Pixels::BASE, 3), ['compression' => $compression, 'predictor' => 2, 'predictor_tag_only' => true]);

    expect(decodedTiff($decoder, $tiff))->toBe(bin2hex(Pixels::rgba(Pixels::opaque(Pixels::BASE))));
})->with('image decoders')->with(['none', 'packbits']);

it('runs LZW through every code width and a table reset', function (ImageDecoder $decoder): void {
    mt_srand(11);
    $pixels = [];
    for ($i = 0; $i < 160 * 120; $i++) {
        $pixels[] = [mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255), 255];
    }
    $tiff = TiffWriter::write(160, 120, 2, 8, 3, Pixels::samples($pixels, 3), ['compression' => 'lzw']);

    expect(decodedTiff($decoder, $tiff))->toBe(bin2hex(Pixels::rgba($pixels)));
})->with('image decoders');

it('assembles tiles, dropping the padding past the image', function (ImageDecoder $decoder): void {
    $pixels = noisyPixels(37, 21, 3);
    $tiff = TiffWriter::write(37, 21, 2, 8, 4, Pixels::samples($pixels, 4), ['extra_samples' => [2], 'tile' => [16, 16], 'compression' => 'lzw', 'predictor' => 2]);

    expect(decodedTiff($decoder, $tiff))->toBe(bin2hex(Pixels::rgba($pixels)));
})->with('image decoders');

it('refuses layouts the rules leave out', function (ImageDecoder $decoder, array $tiff, string $message): void {
    [$photometric, $bits, $spp, $options] = $tiff;
    $bytes = TiffWriter::write(2, 2, $photometric, $bits, $spp, array_fill(0, 4 * $spp, 1), $options);

    expect(fn () => $decoder->decode($bytes))->toThrow(ImageException::class, $message);
})->with('image decoders')->with([
    'a turned orientation' => [[2, 8, 3, ['orientation' => 3]], 'TIFF image not read: orientation 3'],
    'float samples' => [[1, 32, 1, ['sample_format' => 3]], 'TIFF image not read: sample format 3'],
    'CMYK' => [[5, 8, 4, []], 'TIFF image not read: photometric 5'],
    '12-bit RGB' => [[2, 12, 3, []], 'TIFF image not read: 12-bit RGB'],
]);

it('refuses a cut-off TIFF', function (ImageDecoder $decoder): void {
    $tiff = TiffWriter::write(5, 3, 2, 8, 3, Pixels::samples(Pixels::BASE, 3));

    expect(fn () => $decoder->decode(substr($tiff, 0, 30)))->toThrow(ImageException::class, 'TIFF image could not be read');
})->with('image decoders');

it('refuses a TIFF past the pixel limit before decoding it', function (ImageDecoder $decoder): void {
    $tiff = TiffWriter::write(9000, 9000, 1, 1, 1, [], ['rows_per_strip' => 9000]);

    expect(fn () => $decoder->decode($tiff))->toThrow(ImageException::class, 'A 9000x9000 image is past the limit: sides up to 65535, 67108864 pixels in all.');
})->with('image decoders');
