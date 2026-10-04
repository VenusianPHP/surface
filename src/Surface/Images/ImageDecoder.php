<?php

namespace Surface\Images;

use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Images\ImageDecoder as ImageDecoderContract;
use Surface\Contracts\Images\ImageException;
use Surface\Contracts\Images\ImageFormat;

/**
 * What every driver shares: the format from the leading bytes, the size from
 * the header and the limits checked before any pixel is decoded, a JPEG
 * refused when its data stops before the end marker, and the decoded pixels
 * written into a full RGBA8 framebuffer. A driver only turns bytes into
 * RGBA8.
 */
abstract class ImageDecoder implements ImageDecoderContract
{
    public function __construct(protected FramebufferDriver $framebuffers) {}

    public function decode(string $bytes): Framebuffer
    {
        $format = ImageFormat::sniff($bytes) ?? throw ImageException::unknownFormat();
        [$width, $height] = self::dimensions($format, $bytes);
        if ($width < 1 || $height < 1) {
            throw ImageException::corrupt($format, "it says it is {$width}x{$height}.");
        }
        if ($width > self::MAX_SIDE || $height > self::MAX_SIDE || $width * $height > self::MAX_PIXELS) {
            throw ImageException::tooLarge($width, $height);
        }
        if ($format === ImageFormat::JPEG && ! self::jpegEnds($bytes)) {
            throw ImageException::corrupt($format, 'its data stops before the end marker.');
        }

        [$rgba8, $width, $height] = $this->pixels($format, $bytes);

        return $this->framebuffers->full(FormatSpec::rgba8(), $width, $height)->writeRgba8($rgba8, $width, $height);
    }

    /**
     * The image as RGBA8 bytes, top-left first, with its width and height.
     *
     * @return array{string, int, int}
     * @throws ImageException
     */
    abstract protected function pixels(ImageFormat $format, string $bytes): array;

    /**
     * Width and height from the header alone.
     *
     * @return array{int, int}
     * @throws ImageException When the header is cut short or malformed.
     */
    public static function dimensions(ImageFormat $format, string $bytes): array
    {
        return match ($format) {
            ImageFormat::PNG => self::pngDimensions($bytes),
            ImageFormat::JPEG => self::jpegDimensions($bytes),
            ImageFormat::TIFF => self::tiffDimensions($bytes),
        };
    }

    /** @return array{int, int} */
    private static function pngDimensions(string $bytes): array
    {
        if (strlen($bytes) < 24 || substr($bytes, 12, 4) !== 'IHDR') {
            throw ImageException::corrupt(ImageFormat::PNG, 'it has no IHDR chunk first.');
        }

        return array_values(unpack('Nwidth/Nheight', $bytes, 16));
    }

    /** @return array{int, int} */
    private static function jpegDimensions(string $bytes): array
    {
        foreach (self::jpegSegments($bytes) as [$marker, $at]) {
            // SOF0..SOF15 bar DHT (C4), JPG (C8) and DAC (CC).
            if ($marker >= 0xC0 && $marker <= 0xCF && ! in_array($marker, [0xC4, 0xC8, 0xCC], true)) {
                if ($at + 9 > strlen($bytes)) {
                    break;
                }
                ['height' => $height, 'width' => $width] = unpack('nheight/nwidth', $bytes, $at + 5);

                return [$width, $height];
            }
        }

        throw ImageException::corrupt(ImageFormat::JPEG, 'it has no frame header.');
    }

    /** Whether an end-of-image marker follows the first scan. Entropy-coded data never holds 0xFF 0xD9 unescaped. */
    private static function jpegEnds(string $bytes): bool
    {
        foreach (self::jpegSegments($bytes) as [$marker, $at]) {
            if ($marker === 0xDA) {
                return strpos($bytes, "\xff\xd9", $at) !== false;
            }
        }

        return false;
    }

    /**
     * The marker segments up to and including the first scan, as [marker, offset of its 0xFF].
     *
     * @return iterable<array{int, int}>
     */
    private static function jpegSegments(string $bytes): iterable
    {
        $at = 2;
        $length = strlen($bytes);
        while ($at + 4 <= $length) {
            if ($bytes[$at] !== "\xff") {
                return;
            }
            $marker = ord($bytes[$at + 1]);
            if ($marker === 0xFF) {
                $at++;      // fill byte

                continue;
            }
            yield [$marker, $at];
            if ($marker === 0xDA) {
                return;
            }
            $at += ($marker >= 0xD0 && $marker <= 0xD7) || $marker === 0x01 ? 2 : 2 + unpack('n', $bytes, $at + 2)[1];
        }
    }

    /** @return array{int, int} */
    private static function tiffDimensions(string $bytes): array
    {
        $ifd = TiffDirectory::read($bytes);

        return [$ifd->number(256), $ifd->number(257)];
    }
}
