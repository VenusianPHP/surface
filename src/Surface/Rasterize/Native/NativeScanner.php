<?php

namespace Surface\Rasterize\Native;

use Closure;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\FillRule;
use Surface\Contracts\Rasterize\RasterizeException;
use Surface\Contracts\Rasterize\Scanner;

/**
 * The row work in PHP. ext-rasterize's core.c does the same arithmetic in the
 * same order, so the two answer the same bytes: change one, change both.
 *
 * A shape answers the intervals [a, b) where a horizontal sample line crosses
 * its inside. Hard edges sample each pixel row at its centre and take the
 * pixels whose centres fall in an interval. Anti-aliased edges sample each row
 * on SAMPLES lines and add each interval's exact horizontal overlap with each
 * pixel; the running sum of a difference array carries the fully covered runs.
 */
final class NativeScanner implements Scanner
{
    public const int SAMPLES = 16;

    public function __construct(
        private readonly Region $clip,
        private readonly Edges $edges,
    ) {
        if ($clip->isEmpty() || $clip->x < 0 || $clip->y < 0 || $clip->right() > 0xFFFF || $clip->bottom() > 0xFFFF) {
            throw RasterizeException::clip($clip);
        }
    }

    public function clip(): Region
    {
        return $this->clip;
    }

    public function edges(): Edges
    {
        return $this->edges;
    }

    public function path(array $contours, FillRule $rule): string
    {
        // [ymin, ymax, x0, y0, slope, direction, order]: x on line y is x0 + (y - y0) * slope.
        $edges = [];
        foreach (array_values($contours) as $c => $contour) {
            $flat = self::flat($contour, "Contour {$c}");
            $n = intdiv(count($flat), 2);
            for ($i = 0; $i < $n; $i++) {
                $j = ($i + 1) % $n;
                [$x0, $y0, $x1, $y1] = [$flat[2 * $i], $flat[2 * $i + 1], $flat[2 * $j], $flat[2 * $j + 1]];
                if ($y0 == $y1) {
                    continue;
                }
                $edges[] = [min($y0, $y1), max($y0, $y1), $x0, $y0, ($x1 - $x0) / ($y1 - $y0), $y1 > $y0 ? 1 : -1, count($edges)];
            }
        }
        if ($edges === []) {
            return '';
        }
        usort($edges, fn (array $a, array $b): int => [$a[0], $a[6]] <=> [$b[0], $b[6]]);

        $even_odd = $rule === FillRule::EVEN_ODD;
        $next = 0;
        $active = [];

        // Sample lines arrive in increasing y, so edges join once and leave once.
        return $this->scan(function (float $y) use ($edges, $even_odd, &$next, &$active): array {
            while ($next < count($edges) && $edges[$next][0] <= $y) {
                $active[] = $edges[$next++];
            }
            $active = array_values(array_filter($active, fn (array $e): bool => $e[1] > $y));

            $crossings = [];
            foreach ($active as $e) {
                $crossings[] = [$e[2] + ($y - $e[3]) * $e[4], $e[6], $e[5]];
            }
            usort($crossings, fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

            $intervals = [];
            $winding = 0;
            $start = 0.0;
            foreach ($crossings as [$x, , $direction]) {
                $was = $even_odd ? ($winding & 1) === 1 : $winding !== 0;
                $winding += $even_odd ? 1 : $direction;
                $is = $even_odd ? ($winding & 1) === 1 : $winding !== 0;
                if (! $was && $is) {
                    $start = $x;
                } elseif ($was && ! $is && $x > $start) {
                    $intervals[] = [$start, $x];
                }
            }

            return $intervals;
        });
    }

    public function ellipse(float $cx, float $cy, float $rx, float $ry): string
    {
        self::numbers([$cx, $cy, $rx, $ry]);
        if ($rx <= 0 || $ry <= 0) {
            return '';
        }

        return $this->scan(fn (float $y): array => self::across($cx, $cy, $rx, $ry, $y));
    }

    public function ring(float $cx, float $cy, float $rx, float $ry, float $stroke): string
    {
        self::numbers([$cx, $cy, $rx, $ry, $stroke]);
        if ($rx <= 0 || $ry <= 0 || $stroke <= 0) {
            return '';
        }
        $half = $stroke / 2;

        return $this->scan(function (float $y) use ($cx, $cy, $rx, $ry, $half): array {
            $outer = self::across($cx, $cy, $rx + $half, $ry + $half, $y);
            if ($outer === [] || $rx - $half <= 0 || $ry - $half <= 0) {
                return $outer;
            }
            $inner = self::across($cx, $cy, $rx - $half, $ry - $half, $y);
            if ($inner === []) {
                return $outer;
            }

            return [[$outer[0][0], $inner[0][0]], [$inner[0][1], $outer[0][1]]];
        });
    }

    public function polyline(array $points, bool $closed): string
    {
        $flat = self::flat($points, 'Points');
        $n = intdiv(count($flat), 2);
        if ($n === 0) {
            return '';
        }
        $p = [];
        for ($i = 0; $i < $n; $i++) {
            $p[] = [(int) floor($flat[2 * $i]), (int) floor($flat[2 * $i + 1])];
        }

        $rows = [];
        if ($n === 1) {
            $this->segment($p[0], $p[0], $rows);
        }
        for ($i = 0; $i < $n - 1; $i++) {
            $this->segment($p[$i], $p[$i + 1], $rows);
        }
        if ($closed && $n > 1) {
            $this->segment($p[$n - 1], $p[0], $rows);
        }

        ksort($rows);
        $out = '';
        foreach ($rows as $y => $xs) {
            ksort($xs);
            $start = $end = null;
            foreach (array_keys($xs) as $x) {
                if ($end === $x) {
                    $end++;

                    continue;
                }
                if (! is_null($start)) {
                    $out .= pack('vvvC', $y, $start, $end - $start, 255);
                }
                $start = $x;
                $end = $x + 1;
            }
            $out .= pack('vvvC', $y, $start, $end - $start, 255);
        }

        return $out;
    }

    /**
     * The pixels of one one-pixel line inside the clip: along the major axis
     * from the lower end, step i has minor offset floor((2·|minor|·i + |major|) / (2·|major|)).
     *
     * @param  array{int, int}  $from
     * @param  array{int, int}  $to
     * @param  array<int, array<int, true>>  $rows
     */
    private function segment(array $from, array $to, array &$rows): void
    {
        [$x0, $y0] = $from;
        [$x1, $y1] = $to;
        $steep = abs($y1 - $y0) > abs($x1 - $x0);
        [$a0, $b0, $a1, $b1] = $steep ? [$y0, $x0, $y1, $x1] : [$x0, $y0, $x1, $y1];
        if ($a0 > $a1) {
            [$a0, $b0, $a1, $b1] = [$a1, $b1, $a0, $b0];
        }
        [$low, $high, $minor_low, $minor_high] = $steep
            ? [$this->clip->y, $this->clip->bottom(), $this->clip->x, $this->clip->right()]
            : [$this->clip->x, $this->clip->right(), $this->clip->y, $this->clip->bottom()];

        $major = $a1 - $a0;
        $minor = abs($b1 - $b0);
        $sign = $b1 >= $b0 ? 1 : -1;
        for ($a = max($a0, $low), $last = min($a1, $high - 1); $a <= $last; $a++) {
            $b = $b0 + $sign * ($major === 0 ? 0 : intdiv(2 * $minor * ($a - $a0) + $major, 2 * $major));
            if ($b < $minor_low || $b >= $minor_high) {
                continue;
            }
            if ($steep) {
                $rows[$a][$b] = true;
            } else {
                $rows[$b][$a] = true;
            }
        }
    }

    /**
     * Rows of the clip into span bytes, each sample line's intervals asked of $at.
     *
     * @param  Closure(float): list<array{float, float}>  $at
     */
    private function scan(Closure $at): string
    {
        $left = $this->clip->x;
        $right = $this->clip->right();
        $out = '';

        if ($this->edges === Edges::HARD) {
            for ($row = $this->clip->y; $row < $this->clip->bottom(); $row++) {
                $start = $end = -1;
                foreach ($at($row + 0.5) as [$a, $b]) {
                    $first = (int) self::clamp(ceil($a - 0.5), $left, $right);
                    $stop = (int) self::clamp(ceil($b - 0.5), $left, $right);
                    if ($stop <= $first) {
                        continue;
                    }
                    if ($first === $end) {
                        $end = $stop;

                        continue;
                    }
                    if ($end > $start) {
                        $out .= pack('vvvC', $row, $start, $end - $start, 255);
                    }
                    [$start, $end] = [$first, $stop];
                }
                if ($end > $start) {
                    $out .= pack('vvvC', $row, $start, $end - $start, 255);
                }
            }

            return $out;
        }

        $width = $right - $left;
        $weight = 1.0 / self::SAMPLES;
        $cover = array_fill(0, $width, 0.0);
        $delta = array_fill(0, $width + 1, 0.0);
        for ($row = $this->clip->y; $row < $this->clip->bottom(); $row++) {
            $low = $width;
            $high = -1;
            for ($k = 0; $k < self::SAMPLES; $k++) {
                foreach ($at($row + ($k + 0.5) / self::SAMPLES) as [$a, $b]) {
                    $a = max($a, (float) $left);
                    $b = min($b, (float) $right);
                    if ($b <= $a) {
                        continue;
                    }
                    $ia = (int) floor($a);
                    $ib = (int) floor($b);
                    if ($ia === $ib) {
                        $cover[$ia - $left] += ($b - $a) * $weight;
                    } else {
                        $cover[$ia - $left] += (($ia + 1) - $a) * $weight;
                        if ($ia + 1 < $ib) {
                            $delta[$ia + 1 - $left] += $weight;
                            $delta[$ib - $left] -= $weight;
                        }
                        if ($b > $ib) {
                            $cover[$ib - $left] += ($b - $ib) * $weight;
                        }
                    }
                    $low = min($low, $ia - $left);
                    $high = max($high, $ib - $left);
                }
            }
            if ($high < 0) {
                continue;
            }

            $last = min($high, $width - 1);
            $running = 0.0;
            $start = -1;
            $value = 0;
            for ($i = $low; $i <= $last; $i++) {
                $running += $delta[$i];
                $c = $cover[$i] + $running;
                $v = (int) floor(self::clamp($c, 0.0, 1.0) * 255 + 0.5);
                if ($start >= 0 && $v === $value) {
                    continue;
                }
                if ($start >= 0 && $value > 0) {
                    $out .= pack('vvvC', $row, $left + $start, $i - $start, $value);
                }
                $start = $i;
                $value = $v;
            }
            if ($value > 0) {
                $out .= pack('vvvC', $row, $left + $start, $last + 1 - $start, $value);
            }
            for ($i = $low; $i <= $high; $i++) {
                $delta[$i] = 0.0;
                if ($i < $width) {
                    $cover[$i] = 0.0;
                }
            }
        }

        return $out;
    }

    /**
     * Where line y crosses the inside of an ellipse: [cx - h, cx + h) with h = rx·sqrt(1 - t²), t = (y - cy) / ry.
     *
     * @return list<array{float, float}>
     */
    private static function across(float $cx, float $cy, float $rx, float $ry, float $y): array
    {
        $t = ($y - $cy) / $ry;
        $s = 1.0 - $t * $t;
        if ($s <= 0) {
            return [];
        }
        $h = $rx * sqrt($s);

        return [[$cx - $h, $cx + $h]];
    }

    private static function clamp(float $value, float $low, float $high): float
    {
        return $value < $low ? $low : ($value > $high ? $high : $value);
    }

    /**
     * A flat x, y… list, checked: an even count of finite numbers within LIMIT.
     *
     * @return list<float>
     */
    private static function flat(mixed $list, string $what): array
    {
        if (! is_array($list) || ! array_is_list($list) || count($list) % 2 !== 0) {
            throw new RasterizeException("{$what} is not a flat list of x, y pairs.");
        }
        $flat = [];
        foreach ($list as $value) {
            if (! is_int($value) && ! is_float($value)) {
                throw new RasterizeException("{$what} holds something that is not a number.");
            }
            $flat[] = (float) $value;
        }
        self::numbers($flat);

        return $flat;
    }

    /** @param list<float> $numbers */
    private static function numbers(array $numbers): void
    {
        foreach ($numbers as $value) {
            if (! is_finite($value)) {
                throw RasterizeException::notFinite('A coordinate');
            }
            if (abs($value) > self::LIMIT) {
                throw RasterizeException::pastLimit('A coordinate', $value, self::LIMIT);
            }
        }
    }
}
