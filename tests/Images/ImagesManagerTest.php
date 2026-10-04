<?php

declare(strict_types=1);

use Surface\Contracts\Images\ImageException;
use Surface\Images\Extended\ExtendedImageDecoder;
use Surface\Images\Native\NativeImageDecoder;
use Venusian\Surface\Tests\Support\Images\Pixels;
use Venusian\Surface\Tests\Support\Images\PngWriter;

it('decodes through the configured driver, auto by default: extended when ext-imgdec is loaded, native when not', function (): void {
    $png = PngWriter::write(5, 3, 2, 8, Pixels::samples(Pixels::BASE, 3));

    expect(images()->driver())->toBeInstanceOf(function_exists('imgdec_png') ? ExtendedImageDecoder::class : NativeImageDecoder::class)
        ->and(images()->getDefaultDriver())->toBe('auto')
        ->and(images()->driver('native'))->toBeInstanceOf(NativeImageDecoder::class)
        ->and(images(['images.default' => 'native'])->driver()->driver())->toBe('native')
        ->and(images()->decode($png)->toRgba8())->toBe(Pixels::rgba(Pixels::opaque(Pixels::BASE)));
});

it('mints images on the framebuffer driver config names, or the framebuffers default', function (): void {
    $png = PngWriter::write(1, 1, 0, 8, [9]);

    expect(images()->decode($png)->pointer())->not->toBe(0)                 // framebuffers auto: extended
        ->and(images(['framebuffers.default' => 'native'])->decode($png)->pointer())->toBe(0)
        ->and(images(['framebuffers.default' => 'extended'])->decode($png)->pointer())->not->toBe(0)
        ->and(images(['framebuffers.default' => 'extended', 'images.framebuffers' => 'native'])->decode($png)->pointer())->toBe(0);
})->skip(! class_exists(FbBuffer::class), 'needs ext-fb');

it('gives the extended driver when config asks for it', function (): void {
    expect(images(['images.default' => 'extended'])->driver())->toBeInstanceOf(ExtendedImageDecoder::class);
})->skip(! function_exists('imgdec_png'), 'needs ext-imgdec');

it('says what is missing when config asks for extended without ext-imgdec', function (): void {
    expect(fn () => images(['images.default' => 'extended'])->driver())->toThrow(ImageException::class, 'needs ext-imgdec');
})->skip(function_exists('imgdec_png'), 'ext-imgdec is loaded');
