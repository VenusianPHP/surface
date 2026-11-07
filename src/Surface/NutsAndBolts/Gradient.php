<?php

namespace Surface\NutsAndBolts;

use InvalidArgumentException;

/**
 * A colour that changes with the position, to paint a fill or a stroke with instead of a Color: SVG's linearGradient
 * and radialGradient, Canvas 2D's createLinearGradient() and createRadialGradient(), Skia's gradient shaders. It is
 * given in the units of the path it paints and goes through the transform with it.
 *
 * The stops are baked into a ramp of 256 colours, straight alpha interpolated per channel and rounded to a byte;
 * a point's position along the gradient (0 at the start, 1 at the end, spread past them) picks the ramp entry
 * floor(t × 255 + ½). Every engine does that, so they agree on the colours.
 */
abstract readonly class Gradient
{
    /** The colours of the ramp. */
    public const int STEPS = 256;

    /**
     * @param  list<array{float, Color}>  $stops  [offset 0..1, colour], offsets not falling.
     *
     * @throws InvalidArgumentException When there is no stop, an offset is not a finite 0..1, or the offsets fall.
     */
    public function __construct(public array $stops, public GradientSpread $spread = GradientSpread::PAD)
    {
        if ($stops === [] || ! array_is_list($stops)) {
            throw new InvalidArgumentException('A gradient takes a list of stops, at least one: [offset, colour].');
        }
        $previous = 0.0;
        foreach ($stops as $i => $stop) {
            if (! is_array($stop) || count($stop) !== 2 || ! (is_int($stop[0]) || is_float($stop[0])) || ! $stop[1] instanceof Color) {
                throw new InvalidArgumentException("Stop {$i} is [offset, colour].");
            }
            if (! is_finite($stop[0]) || $stop[0] < 0.0 || $stop[0] > 1.0 || $stop[0] < $previous) {
                throw new InvalidArgumentException("Stop {$i} has the offset {$stop[0]}: offsets are 0..1 and do not fall.");
            }
            $previous = (float) $stop[0];
        }
    }

    /** 'linear' or 'radial'. */
    abstract public function kind(): string;

    /**
     * The matrix that takes a point of the surface to the gradient's own space, where a linear gradient runs from
     * (0, 0) to (1, 0) and a radial one has radius 1 about the origin: t is that space's x, or its distance from
     * the origin.
     *
     * @param  Affine  $transform  The transform the path is drawn through.
     * @return Affine|null  Null when the transform cannot be undone.
     */
    abstract public function toGradientSpace(Affine $transform): ?Affine;

    /** The position t of a point of the gradient's own space along the gradient, spread applied: 0..1. */
    public function position(float $u, float $v): float
    {
        return self::at($this->kind(), $this->spread, $u, $v);
    }

    /** The same from what a recorded gradient keeps: its kind ('linear' or 'radial') and spread. */
    public static function at(string $kind, GradientSpread $spread, float $u, float $v): float
    {
        $t = $kind === 'linear' ? $u : hypot($u, $v);

        return match ($spread) {
            GradientSpread::PAD => min(1.0, max(0.0, $t)),
            GradientSpread::REPEAT => $t - floor($t),
            GradientSpread::REFLECT => (fn (float $m): float => $m > 1.0 ? 2.0 - $m : $m)($t - 2.0 * floor($t / 2.0)),
        };
    }

    /** The ramp entry of a position: floor(t × 255 + ½). */
    public static function index(float $t): int
    {
        return (int) floor($t * (self::STEPS - 1) + 0.5);
    }

    /** @return string 256 colours of 4 bytes, RGBA, straight alpha. */
    public function ramp(): string
    {
        $bytes = array_map(fn (array $stop): array => [(float) $stop[0], self::bytes($stop[1])], $this->stops);
        $out = '';
        for ($i = 0; $i < self::STEPS; $i++) {
            $x = $i / (self::STEPS - 1);
            $k = 0;
            foreach ($bytes as $j => [$offset]) {
                if ($offset <= $x) {
                    $k = $j;
                }
            }
            if ($x < $bytes[0][0]) {
                $colour = $bytes[0][1];
            } elseif ($k === count($bytes) - 1) {
                $colour = $bytes[$k][1];
            } else {
                [$from, $a] = $bytes[$k];
                [$to, $b] = $bytes[$k + 1];
                $u = ($x - $from) / ($to - $from);
                $colour = [(int) round($a[0] + ($b[0] - $a[0]) * $u), (int) round($a[1] + ($b[1] - $a[1]) * $u), (int) round($a[2] + ($b[2] - $a[2]) * $u), (int) round($a[3] + ($b[3] - $a[3]) * $u)];
            }
            $out .= chr($colour[0]).chr($colour[1]).chr($colour[2]).chr($colour[3]);
        }

        return $out;
    }

    /** @return array{int, int, int, int} */
    private static function bytes(Color $c): array
    {
        return [(int) round($c->red * 255), (int) round($c->green * 255), (int) round($c->blue * 255), (int) round($c->alpha * 255)];
    }
}
