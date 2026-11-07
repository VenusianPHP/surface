<?php

namespace Surface\Contracts\Drawing\Effects;

use Surface\Contracts\Drawing\DrawingException;

/**
 * Noise, to grain an image or to drive a displacement: SVG's feTurbulence (the spec's reference code, so the same
 * numbers everywhere) or per-pixel white noise from an integer hash. It is a function of the position on the surface
 * and the seed, not of the layer it fills: moving a layer does not move the grain.
 */
final readonly class Noise extends FilterStep
{
    /** The most octaves of turbulence. */
    public const int MAX_OCTAVES = 10;

    /**
     * @param  float  $frequencyX  Turbulence: the base frequency along x, per pixel; 0 or more.
     * @param  float  $frequencyY  Turbulence: the base frequency along y.
     * @param  int  $octaves  Turbulence: 1..MAX_OCTAVES.
     * @param  int  $seed  The seed: any integer (turbulence rounds it as SVG says).
     * @param  bool  $stitch  Turbulence: SVG's stitchTiles, for the layer's size.
     *
     * @throws DrawingException When a frequency is negative or not finite, or the octaves are out of range.
     */
    public function __construct(
        public NoiseType $type,
        public float $frequencyX = 0.0,
        public float $frequencyY = 0.0,
        public int $octaves = 1,
        public int $seed = 0,
        public bool $stitch = false,
        public ?string $result = null,
    ) {
        if (! is_finite($frequencyX) || ! is_finite($frequencyY) || $frequencyX < 0.0 || $frequencyY < 0.0) {
            throw new DrawingException('A noise frequency is 0 or more.');
        }
        if ($octaves < 1 || $octaves > self::MAX_OCTAVES) {
            throw new DrawingException('Noise takes 1 to '.self::MAX_OCTAVES.' octaves.');
        }
    }

    public static function turbulence(float $frequencyX, ?float $frequencyY = null, int $octaves = 1, int $seed = 0, bool $stitch = false, ?string $result = null): self
    {
        return new self(NoiseType::TURBULENCE, $frequencyX, $frequencyY ?? $frequencyX, $octaves, $seed, $stitch, $result);
    }

    public static function fractal(float $frequencyX, ?float $frequencyY = null, int $octaves = 1, int $seed = 0, bool $stitch = false, ?string $result = null): self
    {
        return new self(NoiseType::FRACTAL, $frequencyX, $frequencyY ?? $frequencyX, $octaves, $seed, $stitch, $result);
    }

    public static function hash(int $seed = 0, ?string $result = null): self
    {
        return new self(NoiseType::HASH, 0.0, 0.0, 1, $seed, false, $result);
    }

    public function reads(): array
    {
        return [];
    }

    public function named(): ?string
    {
        return $this->result;
    }
}
