<?php

namespace Surface\NutsAndBolts;

use InvalidArgumentException;

/** Colours that change along the line from (x0, y0) to (x1, y1), the same across it: SVG's linearGradient. */
final readonly class LinearGradient extends Gradient
{
    /**
     * @param  list<array{float, Color}>  $stops
     *
     * @throws InvalidArgumentException When a number is not finite, the two points are one, or as Gradient.
     */
    public function __construct(
        public float $x0,
        public float $y0,
        public float $x1,
        public float $y1,
        array $stops,
        GradientSpread $spread = GradientSpread::PAD,
    ) {
        foreach ([$x0, $y0, $x1, $y1] as $number) {
            if (! is_finite($number)) {
                throw new InvalidArgumentException('A gradient takes finite numbers.');
            }
        }
        if ($x0 === $x1 && $y0 === $y1) {
            throw new InvalidArgumentException('A linear gradient runs between two points that differ.');
        }
        parent::__construct($stops, $spread);
    }

    public function kind(): string
    {
        return 'linear';
    }

    public function toGradientSpace(Affine $transform): ?Affine
    {
        $dx = $this->x1 - $this->x0;
        $dy = $this->y1 - $this->y0;

        return $transform->multiply(new Affine($dx, $dy, -$dy, $dx, $this->x0, $this->y0))->inverse();
    }
}
