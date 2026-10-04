<?php

namespace Surface\Contracts\Framebuffers;

/**
 * Coverage to paint, as bytes: 7-byte records, little-endian, y (uint16),
 * x (uint16), length (uint16, at least 1), coverage (uint8, 0..255). A
 * rasteriser emits rows ascending, x ascending within a row, no two spans of
 * one list overlapping, coverage 1..255. Framebuffer::paintSpans() takes it.
 */
class Spans
{
    public const int BYTES = 7;

    private function __construct() {}

    public static function pack(int $y, int $x, int $length, int $coverage = 255): string
    {
        if ($y < 0 || $y > 0xFFFF || $x < 0 || $x > 0xFFFF || $length < 1 || $length > 0xFFFF || $coverage < 0 || $coverage > 0xFF) {
            throw new FramebufferException("A span is y, x and length in 0..65535 (length at least 1) and coverage in 0..255, got [{$y}, {$x}, {$length}, {$coverage}].");
        }

        return pack('vvvC', $y, $x, $length, $coverage);
    }

    public static function count(string $spans): int
    {
        if (strlen($spans) % self::BYTES !== 0) {
            throw new FramebufferException('A span list is a whole number of '.self::BYTES.'-byte spans, got '.strlen($spans).' bytes.');
        }

        return intdiv(strlen($spans), self::BYTES);
    }

    /** @return list<array{int, int, int, int}> [y, x, length, coverage] per span */
    public static function unpack(string $spans): array
    {
        $out = [];
        for ($i = 0, $n = self::count($spans); $i < $n; $i++) {
            $out[] = array_values(unpack('vy/vx/vlength/Ccoverage', $spans, $i * self::BYTES));
        }

        return $out;
    }
}
