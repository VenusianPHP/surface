<?php

namespace Surface\Rasterize;

use Surface\Contracts\Rasterize\StrokeCap;
use Surface\Contracts\Rasterize\StrokeJoin;

/**
 * A stroked polyline as contours to fill NON_ZERO: a quad per segment, at each corner the join (a miter, cut to
 * a bevel where the tip would sit more than the miter limit of half-strokes from the corner; a bevel; or a round
 * arc), and at the ends of an open polyline the cap (butt, square or round). Every contour is turned to positive
 * signed area, so overlapping pieces unite instead of cancelling. Runs once in PHP for every driver.
 */
final class Stroker
{
    public const float MITER_LIMIT = 4.0;

    /** A round join or cap strays from its arc by less than this many pixels. */
    private const float FLATNESS = 0.1;

    /**
     * @param  list<array{float, float}>  $points
     * @param  array{float, float}|null  $direction  The way a zero-length stroke runs, for the orientation of a square cap; none: along x.
     * @return list<list<float>> Flat x, y, x, y… contours.
     */
    public static function outline(array $points, float $stroke, bool $closed, StrokeCap $cap = StrokeCap::BUTT, StrokeJoin $join = StrokeJoin::MITER, float $miterLimit = self::MITER_LIMIT, ?array $direction = null): array
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
            // a stroke that was drawn but has no length is a dot under a round cap and a square under a square one (SVG 2); none under a butt cap
            return $n === 1 && count($points) >= 2 && $cap !== StrokeCap::BUTT ? [self::dot($p[0], $half, $cap, $direction ?? [1.0, 0.0])] : [];
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
            $piece = self::join($p[($j + 1) % $n], $directions[$j], $directions[($j + 1) % $segments], $half, $join, $miterLimit);
            if (! is_null($piece)) {
                $contours[] = $piece;
            }
        }

        if (! $closed && $cap !== StrokeCap::BUTT) {
            $contours[] = self::cap($p[0], [-$directions[0][0], -$directions[0][1]], $half, $cap);
            $last = $directions[$segments - 1];
            $contours[] = self::cap($p[$n - 1], $last, $half, $cap);
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
     * What a zero-length stroke draws: a circle the width across (round), or a square the width on a side
     * turned to the direction (square).
     *
     * @param  array{float, float}  $at
     * @param  array{float, float}  $direction  Unit direction.
     * @return list<float>
     */
    private static function dot(array $at, float $half, StrokeCap $cap, array $direction): array
    {
        [$x, $y] = $at;
        if ($cap === StrokeCap::SQUARE) {
            $ux = $direction[0] * $half;
            $uy = $direction[1] * $half;
            $nx = -$uy;
            $ny = $ux;

            return [$x - $ux + $nx, $y - $uy + $ny, $x + $ux + $nx, $y + $uy + $ny, $x + $ux - $nx, $y + $uy - $ny, $x - $ux - $nx, $y - $uy - $ny];
        }
        // a multiple of four, so the four points straight out are vertices
        $steps = (int) (ceil(self::steps(2 * M_PI, $half) / 4) * 4);
        $points = [];
        for ($i = 0; $i < $steps; $i++) {
            $angle = 2 * M_PI * $i / $steps;
            $points[] = $x + $half * cos($angle);
            $points[] = $y + $half * sin($angle);
        }

        return $points;
    }

    /**
     * A polyline cut into the pieces a dash pattern leaves: the pattern is on, off, on, off… (an odd count is
     * repeated), starting $offset into it. A piece that runs round a corner keeps the corner. An on element of
     * length 0 leaves a zero-length piece: a dot under a round cap, as SVG's "0 10" dash makes a dotted line. A
     * closed polyline is walked once round; a dash that runs across its start is one piece, joined there as at any
     * corner, and a dash that never stops is the whole polyline, closed.
     *
     * @param  list<array{float, float}>  $points
     * @param  list<float>  $dash  Lengths, not negative, with a positive sum.
     * @return list<array{points: list<array{float, float}>, closed: bool, direction: array{float, float}}> direction: the way the piece runs where it ends, for a zero-length one's square cap
     */
    public static function dashed(array $points, bool $closed, array $dash, float $offset): array
    {
        $pattern = array_map(floatval(...), $dash);
        if (count($pattern) % 2 === 1) {
            $pattern = [...$pattern, ...$pattern];
        }
        $total = array_sum($pattern);
        if ($total <= 0.0) {
            return [];
        }

        $walk = $points;
        $ring = $closed && count($walk) > 2;
        if ($ring) {
            $walk[] = $walk[0];
        }
        $direction = null;
        for ($i = 0; $i < count($walk) - 1; $i++) {
            $length = hypot($walk[$i + 1][0] - $walk[$i][0], $walk[$i + 1][1] - $walk[$i][1]);
            if ($length > 0.0) {
                $direction = [($walk[$i + 1][0] - $walk[$i][0]) / $length, ($walk[$i + 1][1] - $walk[$i][1]) / $length];
                break;
            }
        }
        if ($direction === null) {
            return [];
        }

        // the pattern's state at the start: which element, and how much of it is left
        $phase = fmod($offset, $total);
        if ($phase < 0) {
            $phase += $total;
        }
        $at = 0;
        while ($phase > $pattern[$at] || ($phase === $pattern[$at] && $pattern[$at] > 0.0)) {
            $phase -= $pattern[$at];
            $at = ($at + 1) % count($pattern);
        }
        $left = $pattern[$at] - $phase;

        $pieces = [];
        $piece = null;
        $x = $walk[0][0];
        $y = $walk[0][1];
        $settle = function () use (&$at, &$left, &$piece, &$pieces, &$x, &$y, &$direction, $pattern): void {
            // elements of no length: an on one is a dot
            while ($left <= 1e-12) {
                if ($at % 2 === 0) {
                    $pieces[] = ['points' => [[$x, $y], [$x, $y]], 'closed' => false, 'direction' => $direction];
                }
                $at = ($at + 1) % count($pattern);
                $left = $pattern[$at];
            }
            if ($at % 2 === 0) {
                $piece = ['points' => [[$x, $y]], 'closed' => false, 'direction' => $direction];
            }
        };
        $settle();
        $startsOn = $piece !== null;
        $startIndex = count($pieces);
        $turnedOff = false;

        for ($i = 0; $i < count($walk) - 1; $i++) {
            [$ax, $ay] = $walk[$i];
            [$bx, $by] = $walk[$i + 1];
            $length = hypot($bx - $ax, $by - $ay);
            if ($length === 0.0) {
                continue;
            }
            $direction = [($bx - $ax) / $length, ($by - $ay) / $length];
            $position = 0.0;
            while ($position < $length) {
                $take = min($left, $length - $position);
                $position += $take;
                $left -= $take;
                $x = $ax + ($bx - $ax) * $position / $length;
                $y = $ay + ($by - $ay) * $position / $length;
                if ($piece !== null) {
                    $piece['points'][] = [$x, $y];
                    $piece['direction'] = $direction;
                }
                if ($left <= 1e-12) {
                    if ($piece !== null) {
                        if (count($piece['points']) >= 2) {
                            $pieces[] = $piece;
                        }
                        $piece = null;
                        $turnedOff = true;
                    }
                    $at = ($at + 1) % count($pattern);
                    $left = $pattern[$at];
                    $settle();
                }
            }
        }

        $endsOn = $piece !== null && count($piece['points']) >= 2;
        if ($ring && $startsOn && $endsOn) {
            if (! $turnedOff) {
                // never stopped: the whole polyline, closed
                return [['points' => $points, 'closed' => true, 'direction' => $direction]];
            }
            // the last dash runs on into the first: one piece, joined at the start
            $first = $pieces[$startIndex];
            $piece['points'] = [...$piece['points'], ...array_slice($first['points'], 1)];
            $piece['direction'] = $first['direction'];
            $pieces[$startIndex] = $piece;
        } elseif ($endsOn) {
            $pieces[] = $piece;
        }

        return $pieces;
    }

    /**
     * The piece that fills the outside of a corner; null where the polyline runs straight on or straight back.
     *
     * @param  array{float, float}  $vertex
     * @param  array{float, float}  $in  Unit direction of the segment ending here.
     * @param  array{float, float}  $out  Unit direction of the segment starting here.
     * @return list<float>|null
     */
    private static function join(array $vertex, array $in, array $out, float $half, StrokeJoin $join, float $miterLimit): ?array
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

        if ($join === StrokeJoin::ROUND) {
            $points = [$vx, $vy, $vx + $ax, $vy + $ay];
            $from = atan2($ay, $ax);
            $sweep = atan2($by, $bx) - $from;
            while ($sweep > M_PI) {
                $sweep -= 2 * M_PI;
            }
            while ($sweep < -M_PI) {
                $sweep += 2 * M_PI;
            }
            $steps = self::steps(abs($sweep), $half);
            for ($i = 1; $i < $steps; $i++) {
                $angle = $from + $sweep * $i / $steps;
                $points[] = $vx + $half * cos($angle);
                $points[] = $vy + $half * sin($angle);
            }
            array_push($points, $vx + $bx, $vy + $by);

            return $points;
        }
        if ($join === StrokeJoin::BEVEL) {
            return [$vx, $vy, $vx + $ax, $vy + $ay, $vx + $bx, $vy + $by];
        }

        // Sum of the two unit outer normals: its length is 2cos(θ/2), and the
        // miter tip sits 2·half / that length from the corner along it.
        $mx = ($ax + $bx) / $half;
        $my = ($ay + $by) / $half;
        $squared = $mx * $mx + $my * $my;
        if ($squared * $miterLimit * $miterLimit < 4.0) {
            return [$vx, $vy, $vx + $ax, $vy + $ay, $vx + $bx, $vy + $by];
        }
        $reach = 2 * $half / $squared;

        return [$vx, $vy, $vx + $ax, $vy + $ay, $vx + $mx * $reach, $vy + $my * $reach, $vx + $bx, $vy + $by];
    }

    /**
     * The cap at an end of an open polyline.
     *
     * @param  array{float, float}  $end
     * @param  array{float, float}  $outward  Unit direction pointing away from the polyline, out of this end.
     * @return list<float>
     */
    private static function cap(array $end, array $outward, float $half, StrokeCap $cap): array
    {
        [$x, $y] = $end;
        // the normal on the left of the outward direction
        $nx = -$outward[1] * $half;
        $ny = $outward[0] * $half;
        if ($cap === StrokeCap::SQUARE) {
            $ox = $outward[0] * $half;
            $oy = $outward[1] * $half;

            return [$x + $nx, $y + $ny, $x + $nx + $ox, $y + $ny + $oy, $x - $nx + $ox, $y - $ny + $oy, $x - $nx, $y - $ny];
        }
        // a half circle from the left normal, through the outward direction, to the right normal
        $from = atan2($ny, $nx);
        // an even count, so the point straight out from the end is one of the vertices
        $steps = self::steps(M_PI, $half);
        $steps += $steps % 2;
        $points = [];
        for ($i = 0; $i <= $steps; $i++) {
            $angle = $from - M_PI * $i / $steps;
            $points[] = $x + $half * cos($angle);
            $points[] = $y + $half * sin($angle);
        }

        return $points;
    }

    /** How many straight pieces an arc of $sweep radians at $radius takes to stray less than FLATNESS. */
    private static function steps(float $sweep, float $radius): int
    {
        if ($radius <= self::FLATNESS) {
            return 2;
        }
        $step = 2 * acos(1 - self::FLATNESS / $radius);

        return max(2, (int) ceil($sweep / $step));
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
