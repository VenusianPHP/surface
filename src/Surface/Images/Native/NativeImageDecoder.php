<?php

namespace Surface\Images\Native;

use Surface\Contracts\Images\ImageFormat;
use Surface\Images\ImageDecoder;

/** PNG and JPEG through ext-gd, TIFF in PHP. */
final class NativeImageDecoder extends ImageDecoder
{
    public function driver(): string
    {
        return 'native';
    }

    protected function pixels(ImageFormat $format, string $bytes): array
    {
        return $format === ImageFormat::TIFF ? TiffReader::rgba8($bytes) : GdReader::rgba8($format, $bytes);
    }
}
