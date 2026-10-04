<?php

namespace Surface\Images\Extended;

use ImgdecException;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Images\ImageException;
use Surface\Contracts\Images\ImageFormat;
use Surface\Images\ImageDecoder;

/** All three formats in C through ext-imgdec: libpng, libjpeg and libtiff. */
final class ExtendedImageDecoder extends ImageDecoder
{
    /** @throws ImageException When ext-imgdec is not loaded in this PHP. */
    public function __construct(FramebufferDriver $framebuffers)
    {
        if (! function_exists('imgdec_png')) {
            throw ImageException::extensionMissing();
        }

        parent::__construct($framebuffers);
    }

    public function driver(): string
    {
        return 'extended';
    }

    protected function pixels(ImageFormat $format, string $bytes): array
    {
        try {
            ['width' => $width, 'height' => $height, 'rgba8' => $rgba8] = match ($format) {
                ImageFormat::PNG => imgdec_png($bytes),
                ImageFormat::JPEG => imgdec_jpeg($bytes),
                ImageFormat::TIFF => imgdec_tiff($bytes),
            };
        } catch (ImgdecException $e) {
            throw $e->getCode() === IMGDEC_UNSUPPORTED
                ? ImageException::unsupported($format, $e->getMessage())
                : ImageException::corrupt($format, $e->getMessage());
        }

        return [$rgba8, $width, $height];
    }
}
