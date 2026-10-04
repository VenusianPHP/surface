<?php

declare(strict_types=1);

namespace Venusian\Surface\Tests\Support\Images;

/**
 * Writes classic TIFF files from sample values, so a test controls every
 * byte: byte order, photometric, depth, extra samples, strips or tiles,
 * compression and predictor. The LZW coder follows libtiff's (MSB-first,
 * early change), so libtiff reads what this writes.
 */
final class TiffWriter
{
    /**
     * @param  list<int>  $samples  Row-major, each pixel's samples interleaved.
     * @param  array{
     *     big_endian?: bool,
     *     compression?: 'none'|'packbits'|'lzw'|'deflate',
     *     predictor?: int,
     *     predictor_tag_only?: bool,
     *     rows_per_strip?: int,
     *     tile?: array{int, int},
     *     extra_samples?: list<int>,
     *     colormap?: list<array{int, int, int}>,
     *     orientation?: int,
     *     planar?: int,
     *     sample_format?: int,
     * }  $options
     */
    public static function write(int $width, int $height, int $photometric, int $bits, int $spp, array $samples, array $options = []): string
    {
        $big = $options['big_endian'] ?? false;
        $compression = $options['compression'] ?? 'none';
        $predictor = $options['predictor'] ?? 1;
        // A Predictor tag over samples written as they are: what an uncompressed file with a stray tag holds.
        $differenced = ($options['predictor_tag_only'] ?? false) ? 1 : $predictor;

        // Separate planes: each sample's plane laid out as a one-sample image, planes one after another.
        $planes = ($options['planar'] ?? 1) === 2 ? array_map(fn (int $p): array => array_values(array_filter($samples, fn (int $i): bool => $i % $spp === $p, ARRAY_FILTER_USE_KEY)), range(0, $spp - 1)) : [$samples];
        $perPlane = count($planes) > 1 ? 1 : $spp;
        $blocks = [];
        foreach ($planes as $plane) {
            [$planeBlocks, $layout] = isset($options['tile'])
                ? self::tiles($width, $height, $bits, $perPlane, $plane, $options['tile'], $differenced, $big)
                : self::strips($width, $height, $bits, $perPlane, $plane, $options['rows_per_strip'] ?? $height, $differenced, $big);
            array_push($blocks, ...$planeBlocks);
        }
        $blocks = array_map(fn (string $block): string => self::compress($block, $compression), $blocks);

        $tags = [
            256 => [4, [$width]],
            257 => [4, [$height]],
            258 => [3, array_fill(0, $spp, $bits)],
            259 => [3, [['none' => 1, 'packbits' => 32773, 'lzw' => 5, 'deflate' => 8][$compression]]],
            262 => [3, [$photometric]],
            274 => [3, [$options['orientation'] ?? 1]],
            277 => [3, [$spp]],
            284 => [3, [$options['planar'] ?? 1]],
            339 => [3, array_fill(0, $spp, $options['sample_format'] ?? 1)],
        ] + $layout;
        if ($predictor !== 1) {
            $tags[317] = [3, [$predictor]];
        }
        if (isset($options['extra_samples'])) {
            $tags[338] = [3, $options['extra_samples']];
        }
        if (isset($options['colormap'])) {
            $tags[320] = [3, [...array_column($options['colormap'], 0), ...array_column($options['colormap'], 1), ...array_column($options['colormap'], 2)]];
        }

        // Header, then the blocks, then the IFD and the values too big for an entry.
        $data = '';
        $offsets = [];
        foreach ($blocks as $block) {
            $offsets[] = 8 + strlen($data);
            $data .= $block.(strlen($block) % 2 ? "\0" : '');
        }
        $offsetTag = isset($options['tile']) ? 324 : 273;
        $countTag = isset($options['tile']) ? 325 : 279;
        $tags[$offsetTag] = [4, $offsets];
        $tags[$countTag] = [4, array_map('strlen', $blocks)];
        ksort($tags);

        $ifd = 8 + strlen($data);
        $extra = $ifd + 2 + 12 * count($tags) + 4;
        $entries = '';
        $values = '';
        foreach ($tags as $tag => [$type, $list]) {
            $packed = implode('', array_map(fn (int $v): string => self::int($v, $type === 3 ? 2 : 4, $big), $list));
            $entries .= self::int($tag, 2, $big).self::int($type, 2, $big).self::int(count($list), 4, $big);
            if (strlen($packed) <= 4) {
                $entries .= str_pad($packed, 4, "\0");
            } else {
                $entries .= self::int($extra + strlen($values), 4, $big);
                $values .= $packed;
            }
        }

        return ($big ? "MM\0*" : "II*\0").self::int($ifd, 4, $big).$data
            .self::int(count($tags), 2, $big).$entries.self::int(0, 4, $big).$values;
    }

    /** @return array{list<string>, array<int, array{int, list<int>}>} */
    private static function strips(int $width, int $height, int $bits, int $spp, array $samples, int $rowsPerStrip, int $predictor, bool $big): array
    {
        $rows = [];
        for ($y = 0; $y < $height; $y++) {
            $rows[] = self::row(array_slice($samples, $y * $width * $spp, $width * $spp), $bits, $spp, $predictor, $big);
        }

        return [array_map('implode', array_chunk($rows, $rowsPerStrip)), [278 => [4, [$rowsPerStrip]]]];
    }

    /** @return array{list<string>, array<int, array{int, list<int>}>} */
    private static function tiles(int $width, int $height, int $bits, int $spp, array $samples, array $tile, int $predictor, bool $big): array
    {
        [$tw, $th] = $tile;
        $blocks = [];
        for ($ty = 0; $ty < $height; $ty += $th) {
            for ($tx = 0; $tx < $width; $tx += $tw) {
                $block = '';
                for ($y = $ty; $y < $ty + $th; $y++) {
                    $row = [];
                    for ($x = $tx; $x < $tx + $tw; $x++) {
                        $inside = $x < $width && $y < $height;
                        for ($s = 0; $s < $spp; $s++) {
                            $row[] = $inside ? $samples[($y * $width + $x) * $spp + $s] : 0;
                        }
                    }
                    $block .= self::row($row, $bits, $spp, $predictor, $big);
                }
                $blocks[] = $block;
            }
        }

        return [$blocks, [322 => [4, [$tw]], 323 => [4, [$th]]]];
    }

    /** @param list<int> $values */
    private static function row(array $values, int $bits, int $spp, int $predictor, bool $big): string
    {
        if ($predictor === 2) {
            $mask = $bits === 16 ? 0xFFFF : 0xFF;
            for ($i = count($values) - 1; $i >= $spp; $i--) {
                $values[$i] = ($values[$i] - $values[$i - $spp]) & $mask;
            }
        }
        if ($bits > 8) {
            // 16 and 32 bits as themselves; other depths only reach a refusal, so any container does.
            return implode('', array_map(fn (int $v): string => self::int($v, $bits === 32 ? 4 : 2, $big), $values));
        }
        if ($bits === 8) {
            return pack('C*', ...$values);
        }

        $bytes = '';
        foreach (array_chunk($values, intdiv(8, $bits)) as $group) {
            $byte = 0;
            foreach ($group as $i => $value) {
                $byte |= $value << (8 - $bits * ($i + 1));
            }
            $bytes .= chr($byte);
        }

        return $bytes;
    }

    private static function compress(string $block, string $compression): string
    {
        return match ($compression) {
            'none' => $block,
            'deflate' => gzcompress($block, 9),
            'packbits' => self::packbits($block),
            'lzw' => self::lzw($block),
        };
    }

    private static function packbits(string $bytes): string
    {
        $out = '';
        $i = 0;
        $n = strlen($bytes);
        while ($i < $n) {
            $run = 1;
            while ($i + $run < $n && $run < 128 && $bytes[$i + $run] === $bytes[$i]) {
                $run++;
            }
            if ($run >= 3) {
                $out .= chr(257 - $run).$bytes[$i];
                $i += $run;

                continue;
            }
            $start = $i;
            while ($i < $n && $i - $start < 128 && ! ($i + 2 < $n && $bytes[$i] === $bytes[$i + 1] && $bytes[$i] === $bytes[$i + 2])) {
                $i++;
            }
            $out .= chr($i - $start - 1).substr($bytes, $start, $i - $start);
        }

        return $out;
    }

    /** libtiff's LZWEncode: codes MSB-first from 9 bits, widened once the next free code passes the width, cleared at 4094. */
    private static function lzw(string $bytes): string
    {
        $bits = '';
        $put = function (int $code, int $width) use (&$bits): void {
            $bits .= str_pad(decbin($code), $width, '0', STR_PAD_LEFT);
        };
        $width = 9;
        $free = 258;
        $table = [];
        $put(256, $width);
        $ent = ord($bytes[0]);
        for ($i = 1, $n = strlen($bytes); $i < $n; $i++) {
            $c = ord($bytes[$i]);
            $key = $ent.','.$c;
            if (isset($table[$key])) {
                $ent = $table[$key];

                continue;
            }
            $put($ent, $width);
            $ent = $c;
            $table[$key] = $free++;
            if ($free === 4094) {
                $table = [];
                $free = 258;
                $put(256, $width);
                $width = 9;
            } elseif ($free > (1 << $width) - 1) {
                $width++;
            }
        }
        $put($ent, $width);
        if (++$free > (1 << $width) - 1) {
            $width++;
        }
        $put(257, $width);

        $bits = str_pad($bits, (int) ceil(strlen($bits) / 8) * 8, '0');

        return implode('', array_map(fn (string $byte): string => chr(bindec($byte)), str_split($bits, 8)));
    }

    private static function int(int $value, int $size, bool $big): string
    {
        return pack($size === 2 ? ($big ? 'n' : 'v') : ($big ? 'N' : 'V'), $value);
    }
}
