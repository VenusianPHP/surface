<?php

namespace Surface\Contracts\Drawing\Effects;

use Surface\Contracts\Drawing\DrawingException;

/**
 * A blur: SVG's feGaussianBlur and CSS blur(), or a spiral disc blur. Gaussian: a standard deviation along x and y
 * (0 along an axis leaves it sharp), approximated as the SVG spec says. Spiral: $samples points on a golden-angle
 * spiral out to a radius, each read with bilinear weights of 1/256, averaged.
 */
final readonly class Blur extends FilterStep
{
    /** The most a spiral takes. */
    public const int MAX_SAMPLES = 64;

    /**
     * The largest standard deviation of a gaussian, in pixels: its boxes read (about 1.9 × deviation) pixels a pixel, a pass for each of
     * three boxes on two axes. A wider blur is a spiral, which reads a fixed number of points however far it reaches. The same on every path.
     */
    public const float MAX_DEVIATION = 64.0;

    /**
     * @param  float  $x  Gaussian: the standard deviation along x. Spiral: the radius.
     * @param  float  $y  Gaussian: the standard deviation along y. Spiral: unused.
     *
     * @throws DrawingException When a number is negative or not finite, a gaussian deviation is past MAX_DEVIATION, or the samples are not 1..MAX_SAMPLES.
     */
    public function __construct(
        public BlurSampling $sampling,
        public float $x,
        public float $y,
        public int $samples = 16,
        public EdgeMode $edge = EdgeMode::NONE,
        public ?string $in = null,
        public ?string $result = null,
    ) {
        if (! is_finite($x) || ! is_finite($y) || $x < 0.0 || $y < 0.0) {
            throw new DrawingException('A blur takes a standard deviation or radius of 0 or more.');
        }
        if ($sampling === BlurSampling::GAUSSIAN && ($x > self::MAX_DEVIATION || $y > self::MAX_DEVIATION)) {
            throw new DrawingException('A gaussian blur takes a standard deviation of '.self::MAX_DEVIATION.' at most; use a spiral for a wider one.');
        }
        if ($samples < 1 || $samples > self::MAX_SAMPLES) {
            throw new DrawingException('A spiral blur takes 1 to '.self::MAX_SAMPLES.' samples.');
        }
    }

    public static function gaussian(float $stdDeviation, ?float $stdDeviationY = null, EdgeMode $edge = EdgeMode::NONE, ?string $in = null, ?string $result = null): self
    {
        return new self(BlurSampling::GAUSSIAN, $stdDeviation, $stdDeviationY ?? $stdDeviation, 16, $edge, $in, $result);
    }

    public static function spiral(float $radius, int $samples = 16, EdgeMode $edge = EdgeMode::NONE, ?string $in = null, ?string $result = null): self
    {
        return new self(BlurSampling::SPIRAL, $radius, $radius, $samples, $edge, $in, $result);
    }

    public function reads(): array
    {
        return [$this->in ?? ''];
    }

    public function named(): ?string
    {
        return $this->result;
    }

    public function reach(): float
    {
        return $this->sampling === BlurSampling::SPIRAL ? $this->x + 1.0 : max($this->x, $this->y) * 3.0 + 2.0;
    }
}
