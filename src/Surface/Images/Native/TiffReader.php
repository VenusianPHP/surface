<?php

namespace Surface\Images\Native;

use Surface\Contracts\Images\ImageException;
use Surface\Contracts\Images\ImageFormat;
use Surface\Images\TiffDirectory;

/**
 * Classic TIFF into RGBA8 in PHP, by ImageDecoder's TIFF rules: the first
 * image; grey, RGB or palette; chunky or separate planes; compression none, PackBits,
 * LZW or Deflate; predictor 1 or 2 (under LZW and Deflate, the codecs it
 * belongs to; libtiff ignores it elsewhere, and so does this). Byte-wide work runs through strtr(),
 * preg_replace() and string XOR so it stays in C; only LZW, PackBits, the
 * predictor and associated alpha step byte by byte.
 */
final class TiffReader
{
    private const int NONE = 1;
    private const int LZW = 5;
    private const int DEFLATE = 8;
    private const int ADOBE_DEFLATE = 32946;
    private const int PACKBITS = 32773;

    /**
     * @return array{string, int, int} RGBA8, width, height.
     * @throws ImageException
     */
    public static function rgba8(string $bytes): array
    {
        $ifd = TiffDirectory::read($bytes);
        $width = $ifd->number(256);
        $height = $ifd->number(257);
        $compression = $ifd->number(259, self::NONE);
        $layout = self::layout($ifd, $compression);
        if (! in_array($compression, [self::NONE, self::LZW, self::DEFLATE, self::ADOBE_DEFLATE, self::PACKBITS], true)) {
            throw self::unsupported("compression {$compression} (the native driver reads none, PackBits, LZW and Deflate)");
        }

        if ($layout['planar'] === 2 && $layout['spp'] > 1) {
            // Each plane reads as a one-sample image; then the planes interleave into chunky rows.
            $planes = [];
            for ($plane = 0; $plane < $layout['spp']; $plane++) {
                $planes[] = self::rows($ifd, $bytes, $width, $height, ['spp' => 1] + $layout, $compression, $plane);
            }
            $rows = self::interleave($planes, intdiv($layout['bits'], 8));
        } else {
            $rows = self::rows($ifd, $bytes, $width, $height, $layout, $compression);
        }

        return [self::convert($rows, $width, $height, $layout, $ifd), $width, $height];
    }

    /**
     * What the samples are, checked against the rules.
     *
     * @return array{photometric: int, bits: int, spp: int, alpha: int, predictor: int, planar: int}
     */
    private static function layout(TiffDirectory $ifd, int $compression): array
    {
        $spp = $ifd->number(277, 1);
        $bits = $ifd->numbers(258, array_fill(0, $spp, 1));
        $photometric = $ifd->number(262);

        foreach ($ifd->numbers(339, [1]) as $format) {
            if ($format !== 1) {
                throw self::unsupported("sample format {$format}");
            }
        }
        if (count(array_unique($bits)) !== 1) {
            throw self::unsupported('samples of mixed depths');
        }
        $bits = $bits[0];
        if (($orientation = $ifd->number(274, 1)) !== 1) {
            throw self::unsupported("orientation {$orientation}");
        }
        if (! in_array($planar = $ifd->number(284, 1), [1, 2], true)) {
            throw self::unsupported("planar configuration {$planar}");
        }
        if (($fill = $ifd->number(266, 1)) !== 1) {
            throw self::unsupported("fill order {$fill}");
        }

        [$colours, $depths, $name] = match ($photometric) {
            0, 1 => [1, [1, 2, 4, 8, 16], 'grey'],
            2 => [3, [8, 16], 'RGB'],
            3 => [1, [1, 2, 4, 8], 'palette'],
            default => throw self::unsupported("photometric {$photometric}"),
        };
        if (! in_array($bits, $depths, true)) {
            throw self::unsupported("{$bits}-bit {$name}");
        }
        if ($spp < $colours) {
            throw self::unsupported("{$name} with {$spp} samples per pixel");
        }
        if ($bits < 8 && $spp > 1) {
            throw self::unsupported("{$bits}-bit samples with extra samples");
        }
        if ($photometric === 3 && $spp > 1) {
            throw self::unsupported('palette with extra samples');
        }

        $predictor = in_array($compression, [self::LZW, self::DEFLATE, self::ADOBE_DEFLATE], true) ? $ifd->number(317, 1) : 1;
        if ($predictor !== 1 && ($predictor !== 2 || $bits < 8)) {
            throw self::unsupported("predictor {$predictor} on {$bits}-bit samples");
        }

        $extra = $spp > $colours ? $ifd->numbers(338, [0])[0] : 0;

        return ['photometric' => $photometric, 'bits' => $bits, 'spp' => $spp, 'alpha' => in_array($extra, [1, 2], true) ? $extra : 0, 'predictor' => $predictor, 'planar' => $planar];
    }

    /**
     * The image's rows, decompressed and un-predicted, each byte-aligned and
     * width × spp × bits wide, joined. With separate planes, $plane picks one
     * (its blocks follow the planes before it) and $layout's spp is 1.
     */
    private static function rows(TiffDirectory $ifd, string $bytes, int $width, int $height, array $layout, int $compression, int $plane = 0): string
    {
        $tiled = $ifd->has(322);
        [$blockWidth, $blockHeight] = $tiled ? [$ifd->number(322), $ifd->number(323)] : [$width, min($ifd->number(278, $height), $height)];
        if ($blockWidth < 1 || $blockHeight < 1) {
            throw TiffDirectory::corrupt("its {$blockWidth}x{$blockHeight} blocks are empty.");
        }
        $offsets = $ifd->numbers($tiled ? 324 : 273);
        $counts = $ifd->numbers($tiled ? 325 : 279);
        $across = $tiled ? intdiv($width + $blockWidth - 1, $blockWidth) : 1;
        $down = intdiv($height + $blockHeight - 1, $blockHeight);
        $first = $plane * $across * $down;
        if (count($offsets) < $first + $across * $down || count($counts) < $first + $across * $down) {
            throw TiffDirectory::corrupt('it lists fewer blocks than the image needs.');
        }

        $rowBytes = intdiv($blockWidth * $layout['spp'] * $layout['bits'] + 7, 8);
        $imageRow = intdiv($width * $layout['spp'] * $layout['bits'] + 7, 8);
        $rows = array_fill(0, $height, '');
        for ($by = 0; $by < $down; $by++) {
            for ($bx = 0; $bx < $across; $bx++) {
                $n = $first + $by * $across + $bx;
                $blockRows = $tiled ? $blockHeight : min($blockHeight, $height - $by * $blockHeight);
                if ($offsets[$n] + $counts[$n] > strlen($bytes)) {
                    throw TiffDirectory::corrupt("block {$n} lies outside the file.");
                }
                $block = self::decompress(substr($bytes, $offsets[$n], $counts[$n]), $compression, $blockRows * $rowBytes, $n);
                for ($r = 0; $r < $blockRows; $r++) {
                    $y = $by * $blockHeight + $r;
                    if ($y >= $height) {
                        break;
                    }
                    $row = substr($block, $r * $rowBytes, $rowBytes);
                    if ($layout['predictor'] === 2) {
                        $row = self::unpredict($row, $layout['spp'], $layout['bits'], $ifd->bigEndian);
                    }
                    $rows[$y] .= $row;
                }
            }
        }

        // Tiles pad past the right edge; whole bytes only, since predictor and tiles both need 8 or 16 bits.
        return $tiled ? implode('', array_map(fn (string $row): string => substr($row, 0, $imageRow), $rows)) : implode('', $rows);
    }

    /**
     * Planes of $size-byte samples into chunky samples: each plane spread out with
     * zeros where the others go, then all of them ORed together, byte for byte.
     *
     * @param  list<string>  $planes
     */
    private static function interleave(array $planes, int $size): string
    {
        $count = count($planes);
        $out = null;
        foreach ($planes as $p => $plane) {
            $spread = self::replace("/(.{{$size}})/s", str_repeat("\0", $p * $size).'$1'.str_repeat("\0", ($count - 1 - $p) * $size), $plane);
            $out = is_null($out) ? $spread : $out | $spread;
        }

        return $out ?? '';
    }

    private static function decompress(string $data, int $compression, int $expected, int $block): string
    {
        $out = match ($compression) {
            self::NONE => $data,
            self::PACKBITS => self::packbits($data, $expected),
            self::LZW => self::lzw($data, $expected),
            self::DEFLATE, self::ADOBE_DEFLATE => @gzuncompress($data),
        };
        if ($out === false || strlen($out) < $expected) {
            throw TiffDirectory::corrupt("block {$block} holds less data than its rows need.");
        }

        return $out;
    }

    private static function packbits(string $data, int $expected): string
    {
        $out = '';
        $at = 0;
        $length = strlen($data);
        while ($at < $length && strlen($out) < $expected) {
            $n = ord($data[$at++]);
            if ($n < 128) {
                $out .= substr($data, $at, $n + 1);
                $at += $n + 1;
            } elseif ($n > 128 && $at < $length) {
                $out .= str_repeat($data[$at++], 257 - $n);
            }
        }

        return $out;
    }

    /** libtiff's LZWDecode: codes MSB-first from 9 bits, one wider once the next free code reaches 2^n − 1, reset by code 256, ended by 257. */
    private static function lzw(string $data, int $expected): string
    {
        $table = array_map('chr', range(0, 255));
        $out = [];
        $made = 0;
        $width = 9;
        $free = 258;
        $old = -1;
        $acc = 0;
        $held = 0;
        $at = 0;
        $length = strlen($data);

        while ($made < $expected) {
            while ($held < $width && $at < $length) {
                $acc = (($acc << 8) | ord($data[$at++])) & 0xFFFFFF;
                $held += 8;
            }
            if ($held < $width) {
                break;
            }
            $held -= $width;
            $code = ($acc >> $held) & ((1 << $width) - 1);

            if ($code === 257) {
                break;
            }
            if ($code === 256) {
                $table = array_slice($table, 0, 256);
                $width = 9;
                $free = 258;
                $old = -1;

                continue;
            }
            if ($old === -1) {
                if ($code > 255) {
                    throw TiffDirectory::corrupt("an LZW strip starts with code {$code}.");
                }
                $entry = $table[$code];
            } elseif ($code < $free) {
                $entry = $table[$code];
                if ($free < 4096) {
                    $table[$free++] = $table[$old].$entry[0];
                }
            } elseif ($code === $free) {
                $entry = $table[$old].$table[$old][0];
                if ($free < 4096) {
                    $table[$free++] = $entry;
                }
            } else {
                throw TiffDirectory::corrupt("an LZW strip names code {$code} before it exists.");
            }
            if ($old !== -1 && $free > (1 << $width) - 2 && $width < 12) {
                $width++;
            }
            $out[] = $entry;
            $made += strlen($entry);
            $old = $code;
        }

        return implode('', $out);
    }

    /** Horizontal differencing undone: each sample adds the one spp before it, modulo its width. */
    private static function unpredict(string $row, int $spp, int $bits, bool $big): string
    {
        if ($bits === 8) {
            $values = unpack('C*', $row);
            for ($i = $spp + 1, $n = count($values); $i <= $n; $i++) {
                $values[$i] = ($values[$i] + $values[$i - $spp]) & 0xFF;
            }

            return pack('C*', ...$values);
        }

        $code = $big ? 'n' : 'v';
        $values = unpack("{$code}*", $row);
        for ($i = $spp + 1, $n = count($values); $i <= $n; $i++) {
            $values[$i] = ($values[$i] + $values[$i - $spp]) & 0xFFFF;
        }

        return pack("{$code}*", ...$values);
    }

    /** Joined rows of samples into RGBA8. */
    private static function convert(string $rows, int $width, int $height, array $layout, TiffDirectory $ifd): string
    {
        ['photometric' => $photometric, 'bits' => $bits, 'spp' => $spp, 'alpha' => $alpha] = $layout;

        if ($bits === 16) {
            // Each sample's high byte: the second of a little-endian pair, the first of a big-endian one.
            $rows = self::replace($ifd->bigEndian ? '/(.)./s' : '/.(.)/s', '$1', $rows);
        }

        if ($photometric === 3) {
            $colormap = $ifd->numbers(320);
            $entries = 1 << $bits;
            if (count($colormap) !== 3 * $entries) {
                throw TiffDirectory::corrupt('its colour map does not hold 3 × 2^'.$bits.' entries.');
            }
            $colours = [];
            for ($i = 0; $i < $entries; $i++) {
                $colours[chr($i)] = chr($colormap[$i] >> 8).chr($colormap[$entries + $i] >> 8).chr($colormap[2 * $entries + $i] >> 8)."\xff";
            }

            return strtr(self::widen($rows, $width, $height, $bits, false), $colours);
        }

        $skip = $spp - ($photometric === 2 ? 3 : 1) - ($alpha ? 1 : 0);
        $rest = $skip > 0 ? "(?:.{{$skip}})" : '';
        if ($photometric === 2) {
            $rgba = $alpha
                ? ($skip > 0 ? self::replace("/(...)(.){$rest}/s", '$1$2', $rows) : $rows)
                : self::replace("/(...){$rest}/s", "\$1\xff", $rows);
        } else {
            $greys = $bits < 8 ? self::widen($rows, $width, $height, $bits, true) : $rows;
            $rgba = $alpha
                ? self::replace("/(.)(.){$rest}/s", '$1$1$1$2', $greys)
                : self::replace("/(.){$rest}/s", "\$1\$1\$1\xff", $greys);
            if ($photometric === 0) {
                $rgba ^= str_repeat("\xff\xff\xff\x00", $width * $height);
            }
        }

        return $alpha === 1 ? self::unassociate($rgba) : $rgba;
    }

    /**
     * Sub-byte samples one per byte, rows' padding bits dropped; greys scaled across 0..255.
     */
    private static function widen(string $rows, int $width, int $height, int $bits, bool $scale): string
    {
        if ($bits === 8) {
            return $rows;
        }

        $per = intdiv(8, $bits);
        $max = (1 << $bits) - 1;
        $table = [];
        for ($byte = 0; $byte < 256; $byte++) {
            $samples = '';
            for ($i = 0; $i < $per; $i++) {
                $value = ($byte >> (8 - $bits * ($i + 1))) & $max;
                $samples .= chr($scale ? intdiv($value * 255, $max) : $value);
            }
            $table[chr($byte)] = $samples;
        }

        $rowBytes = intdiv($width * $bits + 7, 8);
        $out = [];
        for ($y = 0; $y < $height; $y++) {
            $out[] = substr(strtr(substr($rows, $y * $rowBytes, $rowBytes), $table), 0, $width);
        }

        return implode('', $out);
    }

    /** Associated alpha divided out: c = round(c × 255 ÷ a), capped at 255; nothing where a is 0. */
    private static function unassociate(string $rgba): string
    {
        $values = unpack('C*', $rgba);
        for ($i = 1, $n = count($values); $i <= $n; $i += 4) {
            $a = $values[$i + 3];
            for ($c = 0; $c < 3; $c++) {
                $values[$i + $c] = $a === 0 ? 0 : min(255, intdiv($values[$i + $c] * 255 + ($a >> 1), $a));
            }
        }

        return pack('C*', ...$values);
    }

    private static function replace(string $pattern, string $replacement, string $subject): string
    {
        return preg_replace($pattern, $replacement, $subject) ?? throw TiffDirectory::corrupt('its samples could not be rearranged ('.preg_last_error_msg().').');
    }

    private static function unsupported(string $what): ImageException
    {
        return ImageException::unsupported(ImageFormat::TIFF, $what);
    }
}
