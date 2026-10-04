<?php

declare(strict_types=1);

use Surface\Framebuffers\Native\NativeFramebufferDriver;
use Surface\Images\Extended\ExtendedImageDecoder;
use Surface\Images\Native\NativeImageDecoder;
use Venusian\Surface\Tests\Support\Images\Pixels;
use Venusian\Surface\Tests\Support\Images\PngWriter;

/*
 * Both drivers decode JPEG with the same libjpeg defaults, so they answer the
 * same bytes; PNG the same but for gd's 7-bit alpha (Pixels::asPng). A
 * 16-bit tRNS colour is where they part: gd sees only the high bytes.
 */

beforeEach(function (): void {
    if (! function_exists('imgdec_png')) {
        $this->markTestSkipped('needs ext-imgdec');
    }
    $this->native = new NativeImageDecoder(new NativeFramebufferDriver());
    $this->extended = new ExtendedImageDecoder(new NativeFramebufferDriver());
});

it('decodes JPEG to the same bytes in both drivers', function (Closure $jpeg) {
    $bytes = $jpeg();

    expect($this->extended->decode($bytes)->toRgba8())->toBe($this->native->decode($bytes)->toRgba8());
})->with([
    'baseline, gd-written' => fn () => Pixels::gdJpeg(false)[0],
    'progressive, gd-written' => fn () => Pixels::gdJpeg(true)[0],
    'grey' => fn () => file_get_contents(__DIR__.'/fixtures/grey.jpg'),
    'CMYK under an Adobe marker' => fn () => file_get_contents(__DIR__.'/fixtures/cmyk.jpg'),
]);

it('decodes PNG to the same bytes in both drivers but for gd\'s alpha', function () {
    mt_srand(17);
    $pixels = [];
    for ($i = 0; $i < 37 * 23; $i++) {
        $pixels[] = [mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)];
    }
    $png = PngWriter::write(37, 23, 6, 8, Pixels::samples($pixels, 4), interlaced: true);

    expect(Pixels::asPng('native', $this->extended->decode($png)->toRgba8()))->toBe($this->native->decode($png)->toRgba8())
        ->and($this->extended->decode($png)->toRgba8())->toBe(Pixels::rgba($pixels));
});

it('matches a 16-bit tRNS colour on all 16 bits in C and on the high bytes through gd', function () {
    // Two pixels sharing their high bytes; only the first is the tRNS colour.
    $png = PngWriter::write(2, 1, 2, 16, [0x1234, 0x5678, 0x9ABC, 0x1235, 0x5678, 0x9ABC], trns: [0x1234, 0x5678, 0x9ABC]);

    expect(bin2hex($this->extended->decode($png)->toRgba8()))->toBe('12569a00'.'12569aff')
        ->and(bin2hex($this->native->decode($png)->toRgba8()))->toBe('12569a00'.'12569a00');
});
