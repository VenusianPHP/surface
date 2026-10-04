<?php

declare(strict_types=1);

namespace Venusian\Surface\Tests\Support\Images;

/** Expected pixels for the Images tests. */
final class Pixels
{
    /** A 5x3 picture whose alphas cover 0, 1, the middle and 255. */
    public const array BASE = [
        [255, 0, 0, 255], [0, 255, 0, 255], [0, 0, 255, 255], [255, 255, 255, 255], [0, 0, 0, 255],
        [16, 32, 48, 0], [64, 80, 96, 1], [112, 128, 144, 127], [160, 176, 192, 128], [208, 224, 240, 254],
        [18, 52, 86, 255], [103, 137, 171, 205], [254, 220, 186, 152], [1, 1, 1, 66], [128, 128, 128, 128],
    ];

    /** Grey values and alphas for the same 5x3 grid. */
    public const array GREY = [0, 1, 17, 85, 128, 129, 170, 200, 254, 255, 7, 77, 99, 222, 3];

    public const array GREY_ALPHA = [255, 0, 1, 2, 127, 128, 129, 200, 254, 255, 66, 255, 0, 90, 180];

    /** @param list<array{int, int, int, int}> $pixels */
    public static function rgba(array $pixels): string
    {
        return implode('', array_map(fn (array $p): string => pack('C4', ...$p), $pixels));
    }

    /** @return list<int> */
    public static function samples(array $pixels, int $channels): array
    {
        return array_merge(...array_map(fn (array $p): array => array_slice($p, 0, $channels), $pixels));
    }

    /** $pixels with alpha forced to 255. */
    public static function opaque(array $pixels): array
    {
        return array_map(fn (array $p): array => [$p[0], $p[1], $p[2], 255], $pixels);
    }

    /** The alpha ext-gd hands back for alpha $a: 7 bits kept, then widened as gd's PNG writer does. */
    public static function gdAlpha(int $a): int
    {
        $q = 127 - ($a >> 1);

        return 255 - (($q << 1) + ($q >> 6));
    }

    /** $rgba8 as $driver reads a PNG with these pixels: native keeps gd's 7 alpha bits. */
    public static function asPng(string $driver, string $rgba8): string
    {
        if ($driver !== 'native') {
            return $rgba8;
        }

        $out = '';
        foreach (str_split($rgba8, 4) as $pixel) {
            $out .= substr($pixel, 0, 3).chr(self::gdAlpha(ord($pixel[3])));
        }

        return $out;
    }

    /**
     * A 24x16 gradient written by gd, baseline or progressive, with its source pixels.
     *
     * @return array{string, list<array{int, int, int}>}
     */
    public static function gdJpeg(bool $progressive): array
    {
        $image = imagecreatetruecolor(24, 16);
        $source = [];
        for ($y = 0; $y < 16; $y++) {
            for ($x = 0; $x < 24; $x++) {
                $rgb = [$x * 10, $y * 15, 255 - $x * 5];
                imagesetpixel($image, $x, $y, ($rgb[0] << 16) | ($rgb[1] << 8) | $rgb[2]);
                $source[] = $rgb;
            }
        }
        imageinterlace($image, $progressive);
        ob_start();
        imagejpeg($image, null, 100);

        return [ob_get_clean(), $source];
    }
}
