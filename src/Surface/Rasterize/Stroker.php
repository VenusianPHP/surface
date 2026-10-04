<?php

namespace Surface\Rasterize;

/**
 * A stroked polyline as contours to fill NON_ZERO: a quad per segment with
 * butt ends, and at each corner a miter, or a bevel where the miter tip would
 * sit more than MITER_LIMIT half-strokes from the corner. Every contour is
 * turned to positive signed area, so overlapping pieces unite instead of
 * cancelling. Runs once in PHP for every driver.
 */
final class Stroker
{
    public const float MITER_LIMIT = 4.0;

    /**
     * @param  list<array{float, float}>  $points
     * @return list<list<float>> Flat x, y, x, y… contours.
     */
    public static function outline(array $points, float $stroke, bool $closed): array
    {
        $half = $stroke / 2;

        $p = [];
        foreach ($points as [$x, $y]) {
            if ($p === [] || $p[count($p) - 1] !== [(float) $x, (float) $y]) {
                $p[] = [(float) $x, (float) $y];
            }
        }
        if ($closed && count($p) > 2 && $p[0] === $p[count($p) - 1]) {
            array_pop($p);
        }
        $n = count($p);
        if ($n < 2) {
            return [];
        }
        $closed = $closed && $n > 2;
        $segments = $closed ? $n : $n - 1;

        $contours = [];
        $directions = [];
        for ($i = 0; $i < $segments; $i++) {
            [$ax, $ay] = $p[$i];
            [$bx, $by] = $p[($i + 1) % $n];
            $length = sqrt(($bx - $ax) * ($bx - $ax) + ($by - $ay) * ($by - $ay));
            $ux = ($bx - $ax) / $length;
            $uy = ($by - $ay) / $length;
            $directions[] = [$ux, $uy];
            $nx = -$uy * $half;
            $ny = $ux * $half;
            $contours[] = [$ax + $nx, $ay + $ny, $bx + $nx, $by + $ny, $bx - $nx, $by - $ny, $ax - $nx, $ay - $ny];
        }

        for ($j = 0, $joins = $closed ? $segments : $segments - 1; $j < $joins; $j++) {
            $join = self::join($p[($j + 1) % $n], $directions[$j], $directions[($j + 1) % $segments], $half);
            if (! is_null($join)) {
                $contours[] = $join;
            }
        }

        $out = [];
        foreach ($contours as $contour) {
            $area = self::area($contour);
            if ($area > 0) {
                $out[] = $contour;
            } elseif ($area < 0) {
                $out[] = self::reverse($contour);
            }
        }

        return $out;
    }

    /**
     * The piece that fills the outside of a corner; null where the polyline runs straight on or straight back.
     *
     * @param  array{float, float}  $vertex
     * @param  array{float, float}  $in  Unit direction of the segment ending here.
     * @param  array{float, float}  $out  Unit direction of the segment starting here.
     * @return list<float>|null
     */
    private static function join(array $vertex, array $in, array $out, float $half): ?array
    {
        [$vx, $vy] = $vertex;
        $cross = $in[0] * $out[1] - $in[1] * $out[0];
        if ($cross == 0.0) {
            return null;
        }

        // The outside of the turn is the side away from it.
        $side = $cross > 0 ? -1.0 : 1.0;
        $ax = $side * -$in[1] * $half;
        $ay = $side * $in[0] * $half;
        $bx = $side * -$out[1] * $half;
        $by = $side * $out[0] * $half;

        // Sum of the two unit outer normals: its length is 2cos(θ/2), and the
        // miter tip sits 2·half / that length from the corner along it.
        $mx = ($ax + $bx) / $half;
        $my = ($ay + $by) / $half;
        $squared = $mx * $mx + $my * $my;
        if ($squared * self::MITER_LIMIT * self::MITER_LIMIT < 4.0) {
            return [$vx, $vy, $vx + $ax, $vy + $ay, $vx + $bx, $vy + $by];
        }
        $reach = 2 * $half / $squared;

        return [$vx, $vy, $vx + $ax, $vy + $ay, $vx + $mx * $reach, $vy + $my * $reach, $vx + $bx, $vy + $by];
    }

    /** @param list<float> $flat Twice the signed area (shoelace). */
    private static function area(array $flat): float
    {
        $sum = 0.0;
        $n = intdiv(count($flat), 2);
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $sum += $flat[2 * $i] * $flat[2 * $j + 1] - $flat[2 * $j] * $flat[2 * $i + 1];
        }

        return $sum;
    }

    /**
     * @param  list<float>  $flat
     * @return list<float>
     */
    private static function reverse(array $flat): array
    {
        return array_merge(...array_reverse(array_chunk($flat, 2)));
    }
}
