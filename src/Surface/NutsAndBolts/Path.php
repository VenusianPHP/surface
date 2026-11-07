<?php

namespace Surface\NutsAndBolts;

use InvalidArgumentException;

/**
 * A path: subpaths of straight lines and quadratic and cubic Bézier curves, built the way SVG path data, the
 * Canvas 2D path API and Skia's SkPath build one. A subpath starts with moveTo(); lineTo(), quadTo() and cubicTo()
 * extend it; close() joins its end to its start, and a segment after close() starts a new subpath at that start
 * (Canvas 2D's rule). The engine flattens a path to straight segments with flatten(), by one fixed rule, so every
 * engine draws the same polygon.
 */
final class Path
{
    /** The deepest a curve is split: 2^16 straight pieces at most. */
    private const int DEPTH = 16;

    /** @var list<array<int, float|string>> ['M', x, y], ['L', x, y], ['Q', cx, cy, x, y], ['C', c1x, c1y, c2x, c2y, x, y] or ['Z'] */
    private array $ops = [];

    /** @var array{float, float}|null The start of the open subpath. */
    private ?array $start = null;

    /** @var array{float, float}|null The end of the last segment; after close(), the start it joined. */
    private ?array $current = null;

    /** Whether the last op was close(): the next segment first moves to the start of the subpath it closed. */
    private bool $closed = false;

    public function moveTo(float $x, float $y): static
    {
        self::finite($x, $y);
        $this->ops[] = ['M', $x, $y];
        $this->start = $this->current = [$x, $y];
        $this->closed = false;

        return $this;
    }

    public function lineTo(float $x, float $y): static
    {
        self::finite($x, $y);
        $this->continue();
        $this->ops[] = ['L', $x, $y];
        $this->current = [$x, $y];

        return $this;
    }

    public function quadTo(float $cx, float $cy, float $x, float $y): static
    {
        self::finite($cx, $cy, $x, $y);
        $this->continue();
        $this->ops[] = ['Q', $cx, $cy, $x, $y];
        $this->current = [$x, $y];

        return $this;
    }

    public function cubicTo(float $c1x, float $c1y, float $c2x, float $c2y, float $x, float $y): static
    {
        self::finite($c1x, $c1y, $c2x, $c2y, $x, $y);
        $this->continue();
        $this->ops[] = ['C', $c1x, $c1y, $c2x, $c2y, $x, $y];
        $this->current = [$x, $y];

        return $this;
    }

    /** Joins the end of the subpath to its start; nothing when no subpath is open. */
    public function close(): static
    {
        if ($this->current !== null && ! $this->closed) {
            $this->ops[] = ['Z'];
            $this->current = $this->start;
            $this->closed = true;
        }

        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->ops === [];
    }

    /** @return list<array<int, float|string>> The ops as given: ['M', x, y], ['L', x, y], ['Q', cx, cy, x, y], ['C', c1x, c1y, c2x, c2y, x, y], ['Z']. */
    public function ops(): array
    {
        return $this->ops;
    }

    /** A copy with every point through the matrix: Béziers stay Béziers under an affine map. */
    public function transformed(Affine $matrix): self
    {
        $copy = new self();
        foreach ($this->ops as $op) {
            $name = $op[0];
            if ($name === 'Z') {
                $copy->close();

                continue;
            }
            $points = [];
            for ($i = 1; $i < count($op); $i += 2) {
                $points = [...$points, ...$matrix->apply($op[$i], $op[$i + 1])];
            }
            match ($name) {
                'M' => $copy->moveTo(...$points),
                'L' => $copy->lineTo(...$points),
                'Q' => $copy->quadTo(...$points),
                'C' => $copy->cubicTo(...$points),
            };
        }

        return $copy;
    }

    /**
     * The path as straight segments. A curve is split in halves (de Casteljau) until each piece's control points
     * lie within $tolerance of its chord segment, or 2^16 pieces; the rule is fixed, so the same path gives the
     * same points everywhere. A subpath that was only moved to (no segment, no close()) is left out; one that
     * was drawn but has no length is kept as its point twice: stroked, it is a dot under a round or square cap.
     *
     * @param  float  $tolerance  How far the straight pieces may stray from the curve, in the path's own units (0.25 is a quarter pixel on a device path).
     * @return list<array{points: list<array{float, float}>, closed: bool}>
     *
     * @throws InvalidArgumentException When the tolerance is not a positive finite number.
     */
    public function flatten(float $tolerance = 0.25): array
    {
        if (! is_finite($tolerance) || $tolerance <= 0.0) {
            throw new InvalidArgumentException("A tolerance is a positive number, got {$tolerance}.");
        }

        $out = [];
        $points = [];
        $drawn = false;
        $finish = function (bool $closed) use (&$out, &$points, &$drawn): void {
            if ($closed && count($points) > 1 && $points[0] === $points[count($points) - 1]) {
                array_pop($points);
            }
            if (count($points) === 1 && $drawn) {
                $points = [$points[0], $points[0]];
            }
            if (count($points) >= 2) {
                $out[] = ['points' => $points, 'closed' => $closed];
            }
            $points = [];
            $drawn = false;
        };

        $at = null;
        foreach ($this->ops as $op) {
            switch ($op[0]) {
                case 'M':
                    $finish(false);
                    $points = [[$op[1], $op[2]]];
                    $at = [$op[1], $op[2]];
                    break;
                case 'L':
                    $points[] = [$op[1], $op[2]];
                    $at = [$op[1], $op[2]];
                    $drawn = true;
                    break;
                case 'Q':
                    self::quad($points, $at, $op[1], $op[2], $op[3], $op[4], $tolerance, 0);
                    $at = [$op[3], $op[4]];
                    $drawn = true;
                    break;
                case 'C':
                    self::cubic($points, $at, $op[1], $op[2], $op[3], $op[4], $op[5], $op[6], $tolerance, 0);
                    $at = [$op[5], $op[6]];
                    $drawn = true;
                    break;
                case 'Z':
                    $start = $points[0] ?? null;
                    $drawn = true;
                    $finish(true);
                    $at = $start;
                    break;
            }
        }
        $finish(false);

        return $out;
    }

    /**
     * The length of the path, all its subpaths, a closed one with its closing side: SVG's getTotalLength() and
     * Skia's SkPathMeasure::getLength(). Each curve is measured on a polyline that follows it within $tolerance,
     * so the length is a hair short of the curve's true one.
     */
    public function length(float $tolerance = 0.05): float
    {
        return array_sum(array_column($this->measured($tolerance), 'length'));
    }

    /**
     * The point at $distance along the path, subpath after subpath (SVG's getPointAtLength()), on the curve itself,
     * and the direction the path runs there in radians (the tangent). A distance outside the path is held to its
     * ends. Null for a path with no length.
     *
     * @return array{x: float, y: float, angle: float}|null
     */
    public function pointAt(float $distance, float $tolerance = 0.05): ?array
    {
        $pieces = array_values(array_filter($this->measured($tolerance), fn (array $piece): bool => $piece['length'] > 0.0));
        if ($pieces === []) {
            return null;
        }
        $left = max(0.0, $distance);
        foreach ($pieces as $i => $piece) {
            if ($left <= $piece['length'] || $i === array_key_last($pieces)) {
                $t = self::parameter($piece, min($left, $piece['length']));
                [$x, $y] = self::at($piece, $t);
                [$dx, $dy] = self::tangent($piece, $t);

                return ['x' => $x, 'y' => $y, 'angle' => atan2($dy, $dx)];
            }
            $left -= $piece['length'];
        }

        return null;
    }

    /**
     * The part of the path from distance $from to distance $to, as a new path of the same kind of pieces: a line
     * gives a line, a curve gives the curve between those two parameters (de Casteljau), as Skia's
     * SkPathMeasure::getSegment() does. Each subpath the range touches gives a subpath; the ends are held to the
     * path. An empty range gives an empty path.
     *
     * @throws InvalidArgumentException When $from is after $to or a number is not finite.
     */
    public function segment(float $from, float $to, float $tolerance = 0.05): self
    {
        if (! is_finite($from) || ! is_finite($to) || $from > $to) {
            throw new InvalidArgumentException('A segment runs from a distance to a later one.');
        }
        $part = new self();
        if ($from === $to) {
            return $part;
        }
        $start = 0.0;
        $previous = null;
        foreach ($this->measured($tolerance) as $piece) {
            $end = $start + $piece['length'];
            if ($piece['length'] > 0.0 && $end > $from && $start < $to) {
                $t0 = max(0.0, $from - $start) <= 0.0 ? 0.0 : self::parameter($piece, $from - $start);
                $t1 = $to >= $end ? 1.0 : self::parameter($piece, $to - $start);
                $cut = self::cut($piece, $t0, $t1);
                if ($piece['first'] || $previous !== [$cut[1], $cut[2]]) {
                    $part->moveTo($cut[1], $cut[2]);
                }
                match ($cut[0]) {
                    'L' => $part->lineTo($cut[3], $cut[4]),
                    'Q' => $part->quadTo($cut[3], $cut[4], $cut[5], $cut[6]),
                    'C' => $part->cubicTo($cut[3], $cut[4], $cut[5], $cut[6], $cut[7], $cut[8]),
                };
                $previous = [$cut[count($cut) - 2], $cut[count($cut) - 1]];
            } else {
                $previous = null;
            }
            $start = $end;
        }

        return $part;
    }

    /**
     * Every side of the path as a piece with its length: 'kind' L, Q or C, 'pts' x0, y0, … x1, y1, 'length', 'first'
     * (the first of its subpath), and for a curve 'ts' and 'cum', the parameters and the length run so far at the
     * points of the polyline that follows it, to turn a length back into a parameter.
     *
     * @return list<array{kind: string, pts: list<float>, length: float, first: bool, ts: list<float>, cum: list<float>}>
     */
    private function measured(float $tolerance): array
    {
        if (! is_finite($tolerance) || $tolerance <= 0.0) {
            throw new InvalidArgumentException("A tolerance is a positive number, got {$tolerance}.");
        }
        $pieces = [];
        $at = $start = null;
        $first = true;
        foreach ($this->ops as $op) {
            switch ($op[0]) {
                case 'M':
                    $at = $start = [$op[1], $op[2]];
                    $first = true;
                    break;
                case 'L':
                    $pieces[] = self::piece('L', [...$at, $op[1], $op[2]], $first, $tolerance);
                    $at = [$op[1], $op[2]];
                    $first = false;
                    break;
                case 'Q':
                    $pieces[] = self::piece('Q', [...$at, $op[1], $op[2], $op[3], $op[4]], $first, $tolerance);
                    $at = [$op[3], $op[4]];
                    $first = false;
                    break;
                case 'C':
                    $pieces[] = self::piece('C', [...$at, $op[1], $op[2], $op[3], $op[4], $op[5], $op[6]], $first, $tolerance);
                    $at = [$op[5], $op[6]];
                    $first = false;
                    break;
                case 'Z':
                    if ($at !== null && $at !== $start) {
                        $pieces[] = self::piece('L', [...$at, ...$start], $first, $tolerance);
                    }
                    $at = $start;
                    $first = true;
                    break;
            }
        }

        return $pieces;
    }

    /** @param list<float> $pts @return array{kind: string, pts: list<float>, length: float, first: bool, ts: list<float>, cum: list<float>} */
    private static function piece(string $kind, array $pts, bool $first, float $tolerance): array
    {
        $samples = [[0.0, $pts[0], $pts[1]]];
        match ($kind) {
            'L' => $samples[] = [1.0, $pts[2], $pts[3]],
            'Q' => self::quadSamples($samples, $pts, 0.0, 1.0, $tolerance, 0),
            'C' => self::cubicSamples($samples, $pts, 0.0, 1.0, $tolerance, 0),
        };
        $ts = [];
        $cum = [];
        $run = 0.0;
        foreach ($samples as $i => [$t, $x, $y]) {
            if ($i > 0) {
                $run += hypot($x - $samples[$i - 1][1], $y - $samples[$i - 1][2]);
            }
            $ts[] = $t;
            $cum[] = $run;
        }

        return ['kind' => $kind, 'pts' => $pts, 'length' => $run, 'first' => $first, 'ts' => $ts, 'cum' => $cum];
    }

    /** @param list<array{float, float, float}> $out [t, x, y] @param list<float> $p */
    private static function quadSamples(array &$out, array $p, float $t0, float $t1, float $tolerance, int $depth): void
    {
        if ($depth >= self::DEPTH || self::away($p[2], $p[3], $p[0], $p[1], $p[4], $p[5]) <= $tolerance) {
            $out[] = [$t1, $p[4], $p[5]];

            return;
        }
        [$left, $right] = self::splitQuad($p, 0.5);
        $mid = ($t0 + $t1) / 2;
        self::quadSamples($out, $left, $t0, $mid, $tolerance, $depth + 1);
        self::quadSamples($out, $right, $mid, $t1, $tolerance, $depth + 1);
    }

    /** @param list<array{float, float, float}> $out [t, x, y] @param list<float> $p */
    private static function cubicSamples(array &$out, array $p, float $t0, float $t1, float $tolerance, int $depth): void
    {
        if ($depth >= self::DEPTH
            || max(self::away($p[2], $p[3], $p[0], $p[1], $p[6], $p[7]), self::away($p[4], $p[5], $p[0], $p[1], $p[6], $p[7])) <= $tolerance) {
            $out[] = [$t1, $p[6], $p[7]];

            return;
        }
        [$left, $right] = self::splitCubic($p, 0.5);
        $mid = ($t0 + $t1) / 2;
        self::cubicSamples($out, $left, $t0, $mid, $tolerance, $depth + 1);
        self::cubicSamples($out, $right, $mid, $t1, $tolerance, $depth + 1);
    }

    /** The parameter at a length $s into the piece: linear between the samples that bracket it. */
    private static function parameter(array $piece, float $s): float
    {
        if ($piece['kind'] === 'L') {
            return $piece['length'] > 0.0 ? min(1.0, max(0.0, $s / $piece['length'])) : 0.0;
        }
        $cum = $piece['cum'];
        $last = count($cum) - 1;
        if ($s <= 0.0) {
            return 0.0;
        }
        if ($s >= $cum[$last]) {
            return 1.0;
        }
        for ($i = 1; $i <= $last; $i++) {
            if ($s <= $cum[$i]) {
                $span = $cum[$i] - $cum[$i - 1];

                return $span > 0.0 ? $piece['ts'][$i - 1] + ($piece['ts'][$i] - $piece['ts'][$i - 1]) * ($s - $cum[$i - 1]) / $span : $piece['ts'][$i];
            }
        }

        return 1.0;
    }

    /** @return array{float, float} The point of the piece at parameter $t. */
    private static function at(array $piece, float $t): array
    {
        $p = $piece['pts'];
        $u = 1.0 - $t;

        return match ($piece['kind']) {
            'L' => [$p[0] * $u + $p[2] * $t, $p[1] * $u + $p[3] * $t],
            'Q' => [$u * $u * $p[0] + 2 * $u * $t * $p[2] + $t * $t * $p[4], $u * $u * $p[1] + 2 * $u * $t * $p[3] + $t * $t * $p[5]],
            'C' => [
                $u ** 3 * $p[0] + 3 * $u * $u * $t * $p[2] + 3 * $u * $t * $t * $p[4] + $t ** 3 * $p[6],
                $u ** 3 * $p[1] + 3 * $u * $u * $t * $p[3] + 3 * $u * $t * $t * $p[5] + $t ** 3 * $p[7],
            ],
        };
    }

    /** @return array{float, float} The direction of the piece at parameter $t (its derivative; the chord where that is zero). */
    private static function tangent(array $piece, float $t): array
    {
        $p = $piece['pts'];
        $u = 1.0 - $t;
        [$dx, $dy] = match ($piece['kind']) {
            'L' => [$p[2] - $p[0], $p[3] - $p[1]],
            'Q' => [2 * $u * ($p[2] - $p[0]) + 2 * $t * ($p[4] - $p[2]), 2 * $u * ($p[3] - $p[1]) + 2 * $t * ($p[5] - $p[3])],
            'C' => [
                3 * $u * $u * ($p[2] - $p[0]) + 6 * $u * $t * ($p[4] - $p[2]) + 3 * $t * $t * ($p[6] - $p[4]),
                3 * $u * $u * ($p[3] - $p[1]) + 6 * $u * $t * ($p[5] - $p[3]) + 3 * $t * $t * ($p[7] - $p[5]),
            ],
        };
        if ($dx === 0.0 && $dy === 0.0) {
            $n = count($p);

            return [$p[$n - 2] - $p[0], $p[$n - 1] - $p[1]];
        }

        return [$dx, $dy];
    }

    /**
     * The piece between parameters $t0 and $t1, as [kind, x0, y0, …]: two de Casteljau splits, at $t1 and then at
     * $t0 of that. The whole piece (0 to 1) comes back as it was given.
     *
     * @return list<float|string>
     */
    private static function cut(array $piece, float $t0, float $t1): array
    {
        $p = $piece['pts'];
        $kind = $piece['kind'];
        if ($t0 <= 0.0 && $t1 >= 1.0) {
            return [$kind, ...$p];
        }
        if ($kind === 'L') {
            [$ax, $ay] = self::at($piece, $t0);
            [$bx, $by] = self::at($piece, $t1);

            return ['L', $ax, $ay, $bx, $by];
        }
        $split = $kind === 'Q' ? self::splitQuad(...) : self::splitCubic(...);
        $head = $t1 >= 1.0 ? $p : $split($p, $t1)[0];
        $tail = $t0 <= 0.0 ? $head : $split($head, $t1 >= 1.0 ? $t0 : $t0 / $t1)[1];

        return [$kind, ...$tail];
    }

    /** @param list<float> $p x0, y0, cx, cy, x1, y1 @return array{list<float>, list<float>} the quadratic split at $t */
    private static function splitQuad(array $p, float $t): array
    {
        $u = 1.0 - $t;
        $ax = $p[0] * $u + $p[2] * $t;
        $ay = $p[1] * $u + $p[3] * $t;
        $bx = $p[2] * $u + $p[4] * $t;
        $by = $p[3] * $u + $p[5] * $t;
        $mx = $ax * $u + $bx * $t;
        $my = $ay * $u + $by * $t;

        return [[$p[0], $p[1], $ax, $ay, $mx, $my], [$mx, $my, $bx, $by, $p[4], $p[5]]];
    }

    /** @param list<float> $p x0, y0, c1x, c1y, c2x, c2y, x1, y1 @return array{list<float>, list<float>} the cubic split at $t */
    private static function splitCubic(array $p, float $t): array
    {
        $u = 1.0 - $t;
        $ax = $p[0] * $u + $p[2] * $t;
        $ay = $p[1] * $u + $p[3] * $t;
        $bx = $p[2] * $u + $p[4] * $t;
        $by = $p[3] * $u + $p[5] * $t;
        $cx = $p[4] * $u + $p[6] * $t;
        $cy = $p[5] * $u + $p[7] * $t;
        $dx = $ax * $u + $bx * $t;
        $dy = $ay * $u + $by * $t;
        $ex = $bx * $u + $cx * $t;
        $ey = $by * $u + $cy * $t;
        $mx = $dx * $u + $ex * $t;
        $my = $dy * $u + $ey * $t;

        return [[$p[0], $p[1], $ax, $ay, $dx, $dy, $mx, $my], [$mx, $my, $ex, $ey, $cx, $cy, $p[6], $p[7]]];
    }

    /** Appends the points of the quadratic from $from, after its start. */
    private static function quad(array &$out, array $from, float $cx, float $cy, float $x, float $y, float $tolerance, int $depth): void
    {
        if ($depth >= self::DEPTH || self::away($cx, $cy, $from[0], $from[1], $x, $y) <= $tolerance) {
            $out[] = [$x, $y];

            return;
        }
        // split at 0.5
        $ax = ($from[0] + $cx) / 2;
        $ay = ($from[1] + $cy) / 2;
        $bx = ($cx + $x) / 2;
        $by = ($cy + $y) / 2;
        $mx = ($ax + $bx) / 2;
        $my = ($ay + $by) / 2;
        self::quad($out, $from, $ax, $ay, $mx, $my, $tolerance, $depth + 1);
        self::quad($out, [$mx, $my], $bx, $by, $x, $y, $tolerance, $depth + 1);
    }

    /** Appends the points of the cubic from $from, after its start. */
    private static function cubic(array &$out, array $from, float $c1x, float $c1y, float $c2x, float $c2y, float $x, float $y, float $tolerance, int $depth): void
    {
        if ($depth >= self::DEPTH
            || max(self::away($c1x, $c1y, $from[0], $from[1], $x, $y), self::away($c2x, $c2y, $from[0], $from[1], $x, $y)) <= $tolerance) {
            $out[] = [$x, $y];

            return;
        }
        // split at 0.5
        $ax = ($from[0] + $c1x) / 2;
        $ay = ($from[1] + $c1y) / 2;
        $bx = ($c1x + $c2x) / 2;
        $by = ($c1y + $c2y) / 2;
        $cx = ($c2x + $x) / 2;
        $cy = ($c2y + $y) / 2;
        $dx = ($ax + $bx) / 2;
        $dy = ($ay + $by) / 2;
        $ex = ($bx + $cx) / 2;
        $ey = ($by + $cy) / 2;
        $mx = ($dx + $ex) / 2;
        $my = ($dy + $ey) / 2;
        self::cubic($out, $from, $ax, $ay, $dx, $dy, $mx, $my, $tolerance, $depth + 1);
        self::cubic($out, [$mx, $my], $ex, $ey, $cx, $cy, $x, $y, $tolerance, $depth + 1);
    }

    /** The distance from a point to the segment (x0, y0)-(x1, y1). */
    private static function away(float $px, float $py, float $x0, float $y0, float $x1, float $y1): float
    {
        $dx = $x1 - $x0;
        $dy = $y1 - $y0;
        $squared = $dx * $dx + $dy * $dy;
        $t = $squared > 0.0 ? max(0.0, min(1.0, (($px - $x0) * $dx + ($py - $y0) * $dy) / $squared)) : 0.0;

        return hypot($px - ($x0 + $t * $dx), $py - ($y0 + $t * $dy));
    }

    /** A segment after close() starts at the subpath's start; before any moveTo() there is nowhere to start. */
    private function continue(): void
    {
        if ($this->current === null) {
            throw new InvalidArgumentException('A path starts with moveTo().');
        }
        if ($this->closed) {
            $this->ops[] = ['M', $this->start[0], $this->start[1]];
            $this->closed = false;
        }
    }

    private static function finite(float ...$numbers): void
    {
        foreach ($numbers as $number) {
            if (! is_finite($number)) {
                throw new InvalidArgumentException('A path takes finite numbers.');
            }
        }
    }
}
