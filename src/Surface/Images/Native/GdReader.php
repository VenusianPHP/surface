<?php

namespace Surface\Images\Native;

use GdImage;
use Surface\Contracts\Images\ImageException;
use Surface\Contracts\Images\ImageFormat;

/**
 * PNG and JPEG into RGBA8 through ext-gd. gd decodes; getting its pixels out
 * as bytes takes one of three routes, fastest first:
 *
 * - PHP's bundled gd honours imagepng()'s filter flag, so a stored, unfiltered
 *   PNG of the image is its RGBA8 rows behind one filter byte each.
 * - An opaque image (JPEG, or PNG without alpha or tRNS) goes out as a 24-bit
 *   BMP: bottom-up BGR rows, turned into RGBA8 with one preg_replace().
 * - Anything else is read pixel by pixel with imagecolorat().
 *
 * gd holds alpha in 7 bits; every route widens it back as gd's PNG writer
 * does. gd leaves an RGB or grey PNG's tRNS colour opaque, so that colour is
 * made transparent here, matched on 8-bit samples.
 */
final class GdReader
{
    /**
     * @return array{string, int, int} RGBA8, width, height.
     * @throws ImageException
     */
    public static function rgba8(ImageFormat $format, string $bytes): array
    {
        if (! extension_loaded('gd')) {
            throw ImageException::gdMissing($format);
        }

        $png = $format === ImageFormat::PNG ? self::pngHeader($bytes) : null;
        // gd reports why it failed as warnings; they become the exception's reason instead.
        $reasons = [];
        set_error_handler(function (int $level, string $message) use (&$reasons): bool {
            $reasons[] = preg_replace('/^imagecreatefromstring\(\): /', '', $message);

            return true;
        });
        try {
            $image = imagecreatefromstring($bytes);
        } finally {
            restore_error_handler();
        }
        if (! $image instanceof GdImage) {
            throw ImageException::corrupt($format, 'gd could not decode it ('.(implode('; ', $reasons) ?: 'no reason given').').');
        }
        $width = imagesx($image);
        $height = imagesy($image);

        if (! imageistruecolor($image)) {
            $rgba8 = self::viaPalette($image, $width, $height);
        } else {
            $opaque = is_null($png) || (in_array($png['colour'], [0, 2], true) && is_null($png['trns']));
            $rgba8 = (defined('GD_BUNDLED') && GD_BUNDLED ? self::viaStoredPng($image, $width, $height) : null)
                ?? ($opaque ? self::viaBmp($image, $width, $height) : self::viaPixels($image, $width, $height));
        }

        if (! is_null($png) && ! is_null($png['trns']) && in_array($png['colour'], [0, 2], true)) {
            $rgba8 = self::clearTrns($rgba8, $png);
        }

        return [$rgba8, $width, $height];
    }

    /**
     * Colour type, bit depth and the tRNS chunk, from the chunks ahead of the image data.
     *
     * @return array{colour: int, depth: int, trns: ?string}
     */
    private static function pngHeader(string $bytes): array
    {
        $header = ['colour' => ord($bytes[25] ?? "\0"), 'depth' => ord($bytes[24] ?? "\0"), 'trns' => null];
        $at = 8;
        while ($at + 8 <= strlen($bytes)) {
            $length = unpack('N', $bytes, $at)[1];
            $type = substr($bytes, $at + 4, 4);
            if ($type === 'IDAT') {
                break;
            }
            if ($type === 'tRNS') {
                $header['trns'] = substr($bytes, $at + 8, $length);
            }
            $at += 12 + $length;
        }

        return $header;
    }

    /** Null when this gd filtered the rows anyway: its filter flag only binds the bundled build. */
    private static function viaStoredPng(GdImage $image, int $width, int $height): ?string
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        ob_start();
        imagepng($image, null, 0, PNG_FILTER_NONE);
        $png = ob_get_clean();

        $data = '';
        for ($at = 8; $at + 8 <= strlen($png);) {
            $length = unpack('N', $png, $at)[1];
            if (substr($png, $at + 4, 4) === 'IDAT') {
                $data .= substr($png, $at + 8, $length);
            }
            $at += 12 + $length;
        }
        $raw = @gzuncompress($data);
        $stride = 1 + $width * 4;
        if ($raw === false || strlen($raw) !== $stride * $height || ord($png[25]) !== 6) {
            return null;
        }

        $rows = [];
        for ($y = 0; $y < $height; $y++) {
            if ($raw[$y * $stride] !== "\0") {
                return null;
            }
            $rows[] = substr($raw, $y * $stride + 1, $width * 4);
        }

        return implode('', $rows);
    }

    private static function viaBmp(GdImage $image, int $width, int $height): string
    {
        ob_start();
        imagebmp($image, null, false);
        $bmp = ob_get_clean();

        $at = unpack('V', $bmp, 10)[1];
        $bottomUp = unpack('l', $bmp, 22)[1] > 0;
        $stride = (($width * 3 + 3) >> 2) << 2;
        $rows = [];
        for ($y = 0; $y < $height; $y++) {
            $rows[] = substr($bmp, $at + ($bottomUp ? $height - 1 - $y : $y) * $stride, $width * 3);
        }

        return preg_replace('/(.)(.)(.)/s', "\$3\$2\$1\xff", implode('', $rows));
    }

    /**
     * A palette image (gd keeps PNG palettes and greys below 16 bits as one) goes out as an 8-bit BMP of its
     * indices, looked up here: gd's own truecolor conversion zeroes transparent entries' colours in some builds.
     */
    private static function viaPalette(GdImage $image, int $width, int $height): string
    {
        $colours = [];
        for ($i = 0, $n = imagecolorstotal($image); $i < $n; $i++) {
            ['red' => $red, 'green' => $green, 'blue' => $blue, 'alpha' => $alpha] = imagecolorsforindex($image, $i);
            $colours[chr($i)] = chr($red).chr($green).chr($blue).chr(255 - (($alpha << 1) + ($alpha >> 6)));
        }

        ob_start();
        imagebmp($image, null, false);
        $bmp = ob_get_clean();
        if (unpack('v', $bmp, 28)[1] !== 8) {
            // A gd that packs fewer bits per index: read the indices one by one instead.
            $out = [];
            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    $out[] = $colours[chr(imagecolorat($image, $x, $y))];
                }
            }

            return implode('', $out);
        }

        $at = unpack('V', $bmp, 10)[1];
        $bottomUp = unpack('l', $bmp, 22)[1] > 0;
        $stride = (($width + 3) >> 2) << 2;
        $rows = [];
        for ($y = 0; $y < $height; $y++) {
            $rows[] = substr($bmp, $at + ($bottomUp ? $height - 1 - $y : $y) * $stride, $width);
        }

        return strtr(implode('', $rows), $colours);
    }

    private static function viaPixels(GdImage $image, int $width, int $height): string
    {
        $rows = [];
        for ($y = 0; $y < $height; $y++) {
            $row = [];
            for ($x = 0; $x < $width; $x++) {
                $colour = imagecolorat($image, $x, $y);
                $alpha = ($colour >> 24) & 0x7F;
                $row[] = (($colour & 0xFFFFFF) << 8) | (255 - (($alpha << 1) + ($alpha >> 6)));
            }
            $rows[] = pack('N*', ...$row);
        }

        return implode('', $rows);
    }

    /** @param array{colour: int, depth: int, trns: ?string} $png */
    private static function clearTrns(string $rgba8, array $png): string
    {
        $wide = $png['depth'] === 16;
        $sample = fn (int $i): string => chr($wide ? unpack('n', $png['trns'], $i * 2)[1] >> 8 : unpack('n', $png['trns'], $i * 2)[1] & 0xFF);
        if (strlen($png['trns']) < ($png['colour'] === 2 ? 6 : 2)) {
            return $rgba8;
        }
        $grey = $png['colour'] === 0;
        $value = $grey ? unpack('n', $png['trns'])[1] : 0;
        if ($grey && $png['depth'] < 8) {
            $value = intdiv($value * 255, (1 << $png['depth']) - 1);
        }
        $colour = $grey ? str_repeat(chr($wide ? $value >> 8 : $value & 0xFF), 3) : $sample(0).$sample(1).$sample(2);

        $pixels = str_split($rgba8, 4);

        return implode('', str_replace($colour."\xff", $colour."\0", $pixels));
    }
}
