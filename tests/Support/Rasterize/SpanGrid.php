<?php

declare(strict_types=1);

namespace Venusian\Surface\Tests\Support\Rasterize;

use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Framebuffers\Spans;
use Surface\Contracts\Rasterize\Edges;

/** Span bytes as something a person can check by eye, and the promises every span list makes. */
final class SpanGrid
{
    /**
     * One string per clip row: '#' and '.' for hard edges, two hex digits a pixel (space separated) for anti-aliased.
     *
     * @return list<string>
     */
    public static function of(string $spans, Region $clip, Edges $edges): array
    {
        $cells = array_fill(0, $clip->height, array_fill(0, $clip->width, 0));
        foreach (Spans::unpack($spans) as [$y, $x, $length, $coverage]) {
            for ($i = 0; $i < $length; $i++) {
                $cells[$y - $clip->y][$x - $clip->x + $i] = $coverage;
            }
        }

        return array_map(
            fn (array $row): string => $edges === Edges::HARD
                ? implode('', array_map(fn (int $c): string => match ($c) { 0 => '.', 255 => '#', default => '?' }, $row))
                : implode(' ', array_map(fn (int $c): string => sprintf('%02x', $c), $row)),
            $cells,
        );
    }

    /** Rows ascending, x ascending, no overlap, nothing outside the clip, coverage 1..255 (255 only for hard edges). */
    public static function assertWellFormed(string $spans, Region $clip, Edges $edges): void
    {
        $last_y = -1;
        $last_end = -1;
        foreach (Spans::unpack($spans) as $i => [$y, $x, $length, $coverage]) {
            expect($length)->toBeGreaterThanOrEqual(1, "span {$i}: length")
                ->and($coverage)->toBeGreaterThanOrEqual(1, "span {$i}: coverage")
                ->and($edges === Edges::HARD ? $coverage === 255 : true)->toBeTrue("span {$i}: hard coverage")
                ->and($clip->contains($x, $y) && $x + $length <= $clip->right())->toBeTrue("span {$i}: inside the clip")
                ->and($y >= $last_y)->toBeTrue("span {$i}: rows ascending");
            if ($y === $last_y) {
                expect($x)->toBeGreaterThanOrEqual($last_end, "span {$i}: no overlap, x ascending");
            }
            $last_y = $y;
            $last_end = $x + $length;
        }
    }

    /** Summed coverage in pixels. */
    public static function area(string $spans): float
    {
        $sum = 0;
        foreach (Spans::unpack($spans) as [, , $length, $coverage]) {
            $sum += $length * $coverage;
        }

        return $sum / 255;
    }

    /** @return array<string, true> "x,y" of every pixel the spans cover */
    public static function pixels(string $spans): array
    {
        $out = [];
        foreach (Spans::unpack($spans) as [$y, $x, $length]) {
            for ($i = 0; $i < $length; $i++) {
                $out[($x + $i).','.$y] = true;
            }
        }

        return $out;
    }
}
