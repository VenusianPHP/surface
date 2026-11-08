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
        [$left, $top, $right, $bottom] = [INF, INF, -INF, -INF];
        foreach (array_values($contours) as $c => $contour) {
            $flat = self::flat($contour, "Contour {$c}");
            $n = intdiv(count($flat), 2);
            for ($i = 0; $i < $n; $i++) {
                [$left, $right] = [min($left, $flat[2 * $i]), max($right, $flat[2 * $i])];
                [$top, $bottom] = [min($top, $flat[2 * $i + 1]), max($bottom, $flat[2 * $i + 1])];
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
        return $this->scan($left, $top, $right, $bottom, function (float $y) use ($edges, $even_odd, &$next, &$active): array {
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

        return $this->scan($cx - $rx, $cy - $ry, $cx + $rx, $cy + $ry, fn (float $y): array => self::across($cx, $cy, $rx, $ry, $y));
    }

    public function ring(float $cx, float $cy, float $rx, float $ry, float $stroke): string
    {
        self::numbers([$cx, $cy, $rx, $ry, $stroke]);
        if ($rx <= 0 || $ry <= 0 || $stroke <= 0) {
            return '';
        }
        $half = $stroke / 2;

        return $this->scan($cx - ($rx + $half), $cy - ($ry + $half), $cx + ($rx + $half), $cy + ($ry + $half), function (float $y) use ($cx, $cy, $rx, $ry, $half): array {
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

    public function roundRect(float $x0, float $y0, float $x1, float $y1, array $radii): string
    {
        // 1. check the box and the four radii
        self::numbers([$x0, $y0, $x1, $y1]);
        $r = self::four($radii);
        if ($x1 <= $x0 || $y1 <= $y0) {
            return '';
        }

        // 2. one interval a sample line
        return $this->scan($x0, $y0, $x1, $y1, fn (float $y): array => self::roundAcross($x0, $y0, $x1, $y1, $r, $y));
    }

    public function roundRing(float $x0, float $y0, float $x1, float $y1, array $radii, float $inset): string
    {
        // 1. check the box, the four radii and the inset
        self::numbers([$x0, $y0, $x1, $y1, $inset]);
        $r = self::four($radii);
        if ($x1 <= $x0 || $y1 <= $y0 || $inset <= 0) {
            return '';
        }

        // 2. the inset box and its radii, max(0, r − inset)
        $ix0 = $x0 + $inset;
        $iy0 = $y0 + $inset;
        $ix1 = $x1 - $inset;
        $iy1 = $y1 - $inset;
        $ir = [max(0.0, $r[0] - $inset), max(0.0, $r[1] - $inset), max(0.0, $r[2] - $inset), max(0.0, $r[3] - $inset)];
        $hollow = $ix1 > $ix0 && $iy1 > $iy0;

        // 3. a line: the outer interval, less the inner one when the line crosses it
        return $this->scan($x0, $y0, $x1, $y1, function (float $y) use ($x0, $y0, $x1, $y1, $r, $ix0, $iy0, $ix1, $iy1, $ir, $hollow): array {
            $outer = self::roundAcross($x0, $y0, $x1, $y1, $r, $y);
            if ($outer === [] || ! $hollow) {
                return $outer;
            }
            $inner = self::roundAcross($ix0, $iy0, $ix1, $iy1, $ir, $y);
            if ($inner === []) {
                return $outer;
            }

            return [[$outer[0][0], $inner[0][0]], [$inner[0][1], $outer[0][1]]];
        });
    }

    public function shadow(float $x0, float $y0, float $x1, float $y1, float $radius, float $blur): string
    {
        // 1. check the box, the radius and the blur
        self::numbers([$x0, $y0, $x1, $y1, $radius, $blur]);
        if ($x1 <= $x0 || $y1 <= $y0) {
            return '';
        }

        // 2. the pixels the blur can reach, cut to the clip
        $left = (int) self::clamp(floor($x0 - $blur), $this->clip->x, $this->clip->right());
        $right = (int) self::clamp(ceil($x1 + $blur), $this->clip->x, $this->clip->right());
        $top = (int) self::clamp(floor($y0 - $blur), $this->clip->y, $this->clip->bottom());
        $bottom = (int) self::clamp(ceil($y1 + $blur), $this->clip->y, $this->clip->bottom());
        if ($right <= $left || $bottom <= $top) {
            return '';
        }

        // 3. the box as centre and half sizes
        $cx = ($x0 + $x1) / 2;
        $cy = ($y0 + $y1) / 2;
        $hx = ($x1 - $x0) / 2;
        $hy = ($y1 - $y0) / 2;

        // 4. columns this far inside both sides see a distance that depends on y alone: one run
        $band = (int) ceil($radius + $blur) + 2;
        $middle_from = (int) ceil($x0) + $band;
        $middle_to = (int) floor($x1) - $band;
        $hard = $this->edges === Edges::HARD;

        // 5. a row: runs of equal coverage, coverage 0 left out
        $out = '';
        for ($row = $top; $row < $bottom; $row++) {
            $py = $row + 0.5;
            $start = $left;
            $value = -1;
            for ($col = $left; $col < $right; $col++) {
                $middle = $col === $middle_from && $middle_to > $middle_from;
                $px = $middle ? $cx : $col + 0.5;
                $v = self::shade(self::roundDistance($px - $cx, $py - $cy, $hx, $hy, $radius), $blur, $hard);
                if ($v !== $value) {
                    if ($value > 0) {
                        $out .= pack('vvvC', $row, $start, $col - $start, $value);
                    }
                    $start = $col;
                    $value = $v;
                }
                if ($middle) {
                    $col = min($middle_to, $right) - 1;
                }
            }
            if ($value > 0) {
                $out .= pack('vvvC', $row, $start, $right - $start, $value);
            }
        }

        return $out;
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
     * Rows of the clip into span bytes, each sample line's intervals asked of $at. Only the rows and columns of the
     * shape's extent (x0, y0)-(x1, y1), a pixel more each side and cut to the clip, are walked: outside it a sample
     * line has no interval, so the spans are the whole clip's.
     *
     * @param  Closure(float): list<array{float, float}>  $at
     */
    private function scan(float $x0, float $y0, float $x1, float $y1, Closure $at): string
    {
        $left = (int) self::clamp(floor($x0) - 1, $this->clip->x, $this->clip->right());
        $right = (int) self::clamp(ceil($x1) + 1, $this->clip->x, $this->clip->right());
        $top = (int) self::clamp(floor($y0) - 1, $this->clip->y, $this->clip->bottom());
        $bottom = (int) self::clamp(ceil($y1) + 1, $this->clip->y, $this->clip->bottom());
        $out = '';
        if ($right <= $left || $bottom <= $top) {
            return $out;
        }

        if ($this->edges === Edges::HARD) {
            for ($row = $top; $row < $bottom; $row++) {
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
        for ($row = $top; $row < $bottom; $row++) {
            $low = $width;
            $high = -1;
            // the columns this row wrote in $cover or $delta; between two of them both are 0, so every pixel has one value
            $touched = [];
            for ($k = 0; $k < self::SAMPLES; $k++) {
                foreach ($at($row + ($k + 0.5) / self::SAMPLES) as [$a, $b]) {
                    $a = max($a, (float) $left);
                    $b = min($b, (float) $right);
                    if ($b <= $a) {
                        continue;
                    }
                    $ia = (int) floor($a);
                    $ib = (int) floor($b);
                    $touched[$ia - $left] = $touched[$ia + 1 - $left] = $touched[$ib - $left] = true;
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
            // many written columns (a grid, a dense path): sorting them costs more than walking the row pixel by pixel
            $dense = count($touched) * 8 >= $last - $low + 1;
            $dense || ksort($touched);
            $columns = array_keys($touched);
            $next = 0;
            for ($i = $low; $i <= $last;) {
                $running += $delta[$i];
                $c = $cover[$i] + $running;
                $v = (int) floor(self::clamp($c, 0.0, 1.0) * 255 + 0.5);
                if (! ($start >= 0 && $v === $value)) {
                    if ($start >= 0 && $value > 0) {
                        $out .= pack('vvvC', $row, $left + $start, $i - $start, $value);
                    }
                    $start = $i;
                    $value = $v;
                }
                if ($dense) {
                    $i++;

                    continue;
                }
                // the pixels up to the next written column hold cover 0 and delta 0: each is $running's value
                while ($next < count($columns) && $columns[$next] <= $i) {
                    $next++;
                }
                $to = $next < count($columns) && $columns[$next] <= $last ? $columns[$next] : $last + 1;
                if ($to > $i + 1) {
                    $w = (int) floor(self::clamp($running, 0.0, 1.0) * 255 + 0.5);
                    if ($w !== $value) {
                        if ($value > 0) {
                            $out .= pack('vvvC', $row, $left + $start, $i + 1 - $start, $value);
                        }
                        $start = $i + 1;
                        $value = $w;
                    }
                }
                $i = $to;
            }
            if ($value > 0) {
                $out .= pack('vvvC', $row, $left + $start, $last + 1 - $start, $value);
            }
            foreach ($columns as $i) {
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

    /**
     * Where line y crosses a rounded box: [left, right). Above a corner's band the end is cut by the
     * quarter circle centred r in from both sides: (corner x) ∓ r·sqrt(1 − t²), t = (y − centre y) / r,
     * written as across() writes the ellipse so a box that is a circle answers the ellipse's bytes.
     *
     * @param  array{float, float, float, float}  $r  [tl, tr, br, bl]
     * @return list<array{float, float}>
     */
    private static function roundAcross(float $x0, float $y0, float $x1, float $y1, array $r, float $y): array
    {
        // 1. outside the box: nothing
        if ($y < $y0 || $y >= $y1) {
            return [];
        }

        // 2. the left end: tl above its band, bl below its band
        $left = $x0;
        if ($y < $y0 + $r[0]) {
            $left = self::corner($x0 + $r[0], $y0 + $r[0], $r[0], $y, -1.0);
        } elseif ($y > $y1 - $r[3]) {
            $left = self::corner($x0 + $r[3], $y1 - $r[3], $r[3], $y, -1.0);
        }

        // 3. the right end: tr above its band, br below its band
        $right = $x1;
        if ($y < $y0 + $r[1]) {
            $right = self::corner($x1 - $r[1], $y0 + $r[1], $r[1], $y, 1.0);
        } elseif ($y > $y1 - $r[2]) {
            $right = self::corner($x1 - $r[2], $y1 - $r[2], $r[2], $y, 1.0);
        }

        // 4. an empty interval is none
        return $right > $left ? [[$left, $right]] : [];
    }

    /** One end on a quarter circle of radius r centred (cx, cy): cx + side·r·sqrt(max(0, 1 − t²)). */
    private static function corner(float $cx, float $cy, float $r, float $y, float $side): float
    {
        $t = ($y - $cy) / $r;
        $s = 1.0 - $t * $t;
        if ($s < 0) {
            $s = 0.0;
        }
        $h = $r * sqrt($s);

        return $side < 0 ? $cx - $h : $cx + $h;
    }

    /** Signed distance from (dx, dy), measured from the box centre, to the box of half sizes hx, hy rounded by r. */
    private static function roundDistance(float $dx, float $dy, float $hx, float $hy, float $r): float
    {
        $qx = abs($dx) - $hx + $r;
        $qy = abs($dy) - $hy + $r;
        $ox = $qx > 0 ? $qx : 0.0;
        $oy = $qy > 0 ? $qy : 0.0;
        $in = $qx > $qy ? $qx : $qy;

        return ($in < 0 ? $in : 0.0) + sqrt($ox * $ox + $oy * $oy) - $r;
    }

    /** Coverage 0..255 at distance d: 1 − smoothstep(−blur, blur, d); a blur of 0 is in when d < 0. */
    private static function shade(float $d, float $blur, bool $hard): int
    {
        if ($blur > 0) {
            $t = self::clamp(($d + $blur) / (2 * $blur), 0.0, 1.0);
            $c = 1.0 - $t * $t * (3 - 2 * $t);
        } else {
            $c = $d < 0 ? 1.0 : 0.0;
        }
        $v = (int) floor($c * 255 + 0.5);
        if ($hard) {
            return $v >= 128 ? 255 : 0;
        }

        return $v;
    }

    /**
     * Four finite radii.
     *
     * @return array{float, float, float, float}
     */
    private static function four(mixed $radii): array
    {
        if (! is_array($radii) || ! array_is_list($radii) || count($radii) !== 4) {
            throw new RasterizeException('Radii are a list of four numbers: [tl, tr, br, bl].');
        }
        $out = [];
        foreach ($radii as $r) {
            if (! is_int($r) && ! is_float($r)) {
                throw new RasterizeException('Radii are a list of four numbers: [tl, tr, br, bl].');
            }
            $out[] = (float) $r;
        }
        self::numbers($out);

        return $out;
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

    public function batch(array $ops): array
    {
        return array_map(fn (array $op): string => match ($op[0] ?? null) {
            self::PATH => $this->path($op[1], $op[2] === 1 ? FillRule::EVEN_ODD : FillRule::NON_ZERO),
            self::ELLIPSE => $this->ellipse($op[1], $op[2], $op[3], $op[4]),
            self::RING => $this->ring($op[1], $op[2], $op[3], $op[4], $op[5]),
            self::ROUND_RECT => $this->roundRect($op[1], $op[2], $op[3], $op[4], $op[5]),
            self::ROUND_RING => $this->roundRing($op[1], $op[2], $op[3], $op[4], $op[5], $op[6]),
            self::SHADOW => $this->shadow($op[1], $op[2], $op[3], $op[4], $op[5], $op[6]),
            self::POLYLINE => $this->polyline($op[1], $op[2]),
            default => throw new RasterizeException('An op is not a list that starts with a Scanner op.'),
        }, $ops);
    }
}
