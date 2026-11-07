<?php

namespace Surface\NutsAndBolts;

use InvalidArgumentException;

/** Colours that change with the distance from (cx, cy), out to the radius: SVG's radialGradient. */
final readonly class RadialGradient extends Gradient
{
    /**
     * @param  list<array{float, Color}>  $stops
     *
     * @throws InvalidArgumentException When a number is not finite, the radius is not above 0, or as Gradient.
     */
    public function __construct(
        public float $cx,
        public float $cy,
        public float $radius,
        array $stops,
        GradientSpread $spread = GradientSpread::PAD,
    ) {
        foreach ([$cx, $cy, $radius] as $number) {
            if (! is_finite($number)) {
                throw new InvalidArgumentException('A gradient takes finite numbers.');
            }
        }
        if ($radius <= 0.0) {
            throw new InvalidArgumentException('A radial gradient has a radius above 0.');
        }
        parent::__construct($stops, $spread);
    }

    public function kind(): string
    {
        return 'radial';
    }

    public function toGradientSpace(Affine $transform): ?Affine
    {
        return $transform->multiply(new Affine($this->radius, 0.0, 0.0, $this->radius, $this->cx, $this->cy))->inverse();
    }
}
