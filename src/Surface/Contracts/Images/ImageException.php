<?php

namespace Surface\Contracts\Images;

use Surface\Contracts\Core\SurfaceException;

class ImageException extends SurfaceException
{
    public static function unknownFormat(): self
    {
        return new self('The bytes are not a PNG, JPEG or TIFF image.');
    }

    public static function corrupt(ImageFormat $format, string $reason): self
    {
        return new self(strtoupper($format->value)." image could not be read: {$reason}");
    }

    public static function unsupported(ImageFormat $format, string $what): self
    {
        return new self(strtoupper($format->value)." image not read: {$what}.");
    }

    public static function tooLarge(int $width, int $height): self
    {
        return new self("A {$width}x{$height} image is past the limit: sides up to ".ImageDecoder::MAX_SIDE.', '.ImageDecoder::MAX_PIXELS.' pixels in all.');
    }

    public static function gdMissing(ImageFormat $format): self
    {
        return new self("The 'native' images driver reads ".strtoupper($format->value).' through ext-gd, which this PHP ('.PHP_BINARY.') does not load.');
    }

    public static function extensionMissing(): self
    {
        return new self("The 'extended' images driver needs ext-imgdec 0.10 or newer loaded in this PHP (".PHP_BINARY.').');
    }
}
