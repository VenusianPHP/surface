<?php

declare(strict_types=1);

namespace Venusian\Surface\Tests\Support\Images;

/**
 * Writes PNG files from sample values, so a test controls every byte: colour
 * type, bit depth, palette, tRNS, interlacing. Rows go in with filter 0.
 */
final class PngWriter
{
    /**
     * @param  list<int>  $samples  Row-major, each pixel's samples interleaved.
     * @param  list<array{int, int, int}>|null  $palette
     * @param  list<int>|null  $trns  Palette alphas, or the one transparent grey / [r, g, b] value.
     */
    public static function write(int $width, int $height, int $colorType, int $bitDepth, array $samples, ?array $palette = null, ?array $trns = null, bool $interlaced = false): string
    {
        $channels = [0 => 1, 2 => 3, 3 => 1, 4 => 2, 6 => 4][$colorType];

        $raw = '';
        if ($interlaced) {
            foreach ([[0, 0, 8, 8], [4, 0, 8, 8], [0, 4, 4, 8], [2, 0, 4, 4], [0, 2, 2, 4], [1, 0, 2, 2], [0, 1, 1, 2]] as [$x0, $y0, $dx, $dy]) {
                for ($y = $y0; $y < $height; $y += $dy) {
                    $row = [];
                    for ($x = $x0; $x < $width; $x += $dx) {
                        array_push($row, ...array_slice($samples, ($y * $width + $x) * $channels, $channels));
                    }
                    if ($row !== []) {
                        $raw .= "\0".self::pack($row, $bitDepth);
                    }
                }
            }
        } else {
            for ($y = 0; $y < $height; $y++) {
                $raw .= "\0".self::pack(array_slice($samples, $y * $width * $channels, $width * $channels), $bitDepth);
            }
        }

        $png = "\x89PNG\r\n\x1a\n".self::chunk('IHDR', pack('NNCCCCC', $width, $height, $bitDepth, $colorType, 0, 0, $interlaced ? 1 : 0));
        if (! is_null($palette)) {
            $png .= self::chunk('PLTE', implode('', array_map(fn (array $c): string => pack('C3', ...$c), $palette)));
        }
        if (! is_null($trns)) {
            $png .= self::chunk('tRNS', $colorType === 3 ? pack('C*', ...$trns) : pack('n*', ...$trns));
        }

        return $png.self::chunk('IDAT', gzcompress($raw, 9)).self::chunk('IEND', '');
    }

    /** @param list<int> $values */
    private static function pack(array $values, int $bits): string
    {
        if ($bits === 16) {
            return pack('n*', ...$values);
        }
        if ($bits === 8) {
            return pack('C*', ...$values);
        }

        $bytes = '';
        $per = intdiv(8, $bits);
        foreach (array_chunk($values, $per) as $group) {
            $byte = 0;
            foreach ($group as $i => $value) {
                $byte |= $value << (8 - $bits * ($i + 1));
            }
            $bytes .= chr($byte);
        }

        return $bytes;
    }

    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
