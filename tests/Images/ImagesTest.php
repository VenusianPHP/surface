<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Images\ImageDecoder;
use Surface\Contracts\Images\ImageException;
use Surface\Contracts\Images\ImageFormat;
use Surface\Framebuffers\Extended\ExtendedFramebufferDriver;
use Surface\Framebuffers\Native\NativeFramebufferDriver;
use Surface\Images\Extended\ExtendedImageDecoder;
use Surface\Images\Native\NativeImageDecoder;
use Venusian\Surface\Tests\Support\Images\Pixels;
use Venusian\Surface\Tests\Support\Images\PngWriter;
use Venusian\Surface\Tests\Support\Images\TiffWriter;

it('tells the three formats apart by their leading bytes', function (): void {
    expect(ImageFormat::sniff(PngWriter::write(1, 1, 0, 8, [0])))->toBe(ImageFormat::PNG)
        ->and(ImageFormat::sniff("\xff\xd8\xff\xe0"))->toBe(ImageFormat::JPEG)
        ->and(ImageFormat::sniff("II*\0"))->toBe(ImageFormat::TIFF)
        ->and(ImageFormat::sniff("MM\0*"))->toBe(ImageFormat::TIFF)
        ->and(ImageFormat::sniff("II+\0"))->toBeNull()     // BigTIFF
        ->and(ImageFormat::sniff('GIF89a'))->toBeNull()
        ->and(ImageFormat::sniff(''))->toBeNull();
});

it('refuses bytes that are no format it reads', function (ImageDecoder $decoder, string $bytes): void {
    expect(fn () => $decoder->decode($bytes))->toThrow(ImageException::class, 'The bytes are not a PNG, JPEG or TIFF image.');
})->with('image decoders')->with(['empty' => '', 'GIF' => 'GIF89a....', 'BigTIFF' => "II+\0\x08\0\0\0"]);

it('names itself', function (): void {
    expect((new NativeImageDecoder(new NativeFramebufferDriver()))->driver())->toBe('native');
    if (function_exists('imgdec_png')) {
        expect((new ExtendedImageDecoder(new NativeFramebufferDriver()))->driver())->toBe('extended');
    }
});

it('mints the image on the framebuffer driver it was given', function (ImageDecoder $decoder): void {
    $png = PngWriter::write(5, 3, 6, 8, Pixels::samples(Pixels::BASE, 4));
    $driver = class_exists(FbBuffer::class) ? new ExtendedFramebufferDriver() : new NativeFramebufferDriver();
    $decoder = new ($decoder::class)($driver);
    $image = $decoder->decode($png);

    expect($image->hostFormat())->toEqual(FormatSpec::rgba8())
        ->and([$image->viewportWidth(), $image->viewportHeight()])->toBe([5, 3])
        ->and($image->pointer() !== 0)->toBe(class_exists(FbBuffer::class));
})->with('image decoders');

it('hands decoded TIFF pixels from both drivers byte for byte the same', function (): void {
    $tiff = TiffWriter::write(5, 3, 2, 8, 4, Pixels::samples(Pixels::BASE, 4), ['extra_samples' => [2], 'compression' => 'lzw']);
    $native = new NativeImageDecoder(new NativeFramebufferDriver());
    $extended = new ExtendedImageDecoder(new NativeFramebufferDriver());

    expect($extended->decode($tiff)->toRgba8())->toBe($native->decode($tiff)->toRgba8());
})->skip(! function_exists('imgdec_png'), 'needs ext-imgdec');

it('refuses to start the extended driver without ext-imgdec', function (): void {
    expect(fn () => new ExtendedImageDecoder(new NativeFramebufferDriver()))->toThrow(ImageException::class, "The 'extended' images driver needs ext-imgdec 0.10 or newer");
})->skip(function_exists('imgdec_png'), 'ext-imgdec is loaded');
