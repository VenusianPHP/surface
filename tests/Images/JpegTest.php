<?php

declare(strict_types=1);

use Surface\Contracts\Images\ImageDecoder;
use Surface\Contracts\Images\ImageException;
use Venusian\Surface\Tests\Support\Images\Pixels;

/*
 * JPEG is lossy, so these tests hold decoded pixels to their source within a
 * tolerance; ImagesParityTest holds the drivers to the same bytes. The
 * fixtures are a grey gradient and a CMYK red | cyan pair written by
 * ImageMagick (CMYK with an Adobe marker, so its samples are inverted).
 */

/** @return list<array{int, int, int, int}> */
function decodedJpeg(ImageDecoder $decoder, string $jpeg): array
{
    return array_chunk(array_values(unpack('C*', $decoder->decode($jpeg)->toRgba8())), 4);
}

it('decodes baseline and progressive JPEG close to the source, opaque', function (ImageDecoder $decoder, bool $progressive): void {
    [$jpeg, $source] = Pixels::gdJpeg($progressive);
    $pixels = decodedJpeg($decoder, $jpeg);
    $worst = 0;
    foreach ($pixels as $i => $pixel) {
        $worst = max($worst, abs($pixel[0] - $source[$i][0]), abs($pixel[1] - $source[$i][1]), abs($pixel[2] - $source[$i][2]));
    }

    expect($decoder->decode($jpeg)->viewportWidth())->toBe(24)
        ->and(count($pixels))->toBe(24 * 16)
        ->and(array_unique(array_column($pixels, 3)))->toBe([255])
        ->and($worst)->toBeLessThanOrEqual(16);
})->with('image decoders')->with(['baseline' => false, 'progressive' => true]);

it('reads grey JPEG as equal channels', function (ImageDecoder $decoder): void {
    $pixels = decodedJpeg($decoder, file_get_contents(__DIR__.'/fixtures/grey.jpg'));

    expect(count($pixels))->toBe(16 * 8)
        ->and(array_filter($pixels, fn (array $p): bool => $p[0] !== $p[1] || $p[1] !== $p[2] || $p[3] !== 255))->toBe([])
        ->and($pixels[0][0])->toBeLessThan(16)
        ->and($pixels[16 * 7][0])->toBeGreaterThan(240);
})->with('image decoders');

it('converts CMYK as gd does, inverted under an Adobe marker', function (ImageDecoder $decoder): void {
    $pixels = decodedJpeg($decoder, file_get_contents(__DIR__.'/fixtures/cmyk.jpg'));
    $near = fn (array $p, array $rgb): bool => abs($p[0] - $rgb[0]) <= 6 && abs($p[1] - $rgb[1]) <= 6 && abs($p[2] - $rgb[2]) <= 6 && $p[3] === 255;

    expect($near($pixels[3 * 16 + 2], [255, 0, 0]))->toBeTrue()
        ->and($near($pixels[3 * 16 + 13], [0, 255, 255]))->toBeTrue();
})->with('image decoders');

it('refuses a cut-off JPEG', function (ImageDecoder $decoder): void {
    [$jpeg] = Pixels::gdJpeg(false);

    expect(fn () => $decoder->decode(substr($jpeg, 0, intdiv(strlen($jpeg), 2))))->toThrow(ImageException::class, 'JPEG image could not be read');
})->with('image decoders');

it('refuses a JPEG past the pixel limit before decoding it', function (ImageDecoder $decoder): void {
    [$jpeg] = Pixels::gdJpeg(false);
    $sof = strpos($jpeg, "\xff\xc0");
    $jpeg = substr_replace($jpeg, pack('nn', 9000, 9000), $sof + 5, 4);

    expect(fn () => $decoder->decode($jpeg))->toThrow(ImageException::class, 'A 9000x9000 image is past the limit: sides up to 65535, 67108864 pixels in all.');
})->with('image decoders');
