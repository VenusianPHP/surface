<?php

namespace Surface\NutsAndBolts;

use InvalidArgumentException;

/**
 * A 2D affine transform: x′ = a·x + c·y + e, y′ = b·x + d·y + f, the layout
 * CSS, SVG and canvas use. Shared by every Surface component that places
 * things: drawing transforms shapes with it, framebuffers place images with it.
 */
readonly class Affine
{
    /** @throws InvalidArgumentException When a component is not finite. */
    public function __construct(
        public float $a,
        public float $b,
        public float $c,
        public float $d,
        public float $e,
        public float $f,
    ) {
        foreach ([$a, $b, $c, $d, $e, $f] as $component) {
            if (! is_finite($component)) {
                throw new InvalidArgumentException("Affine components are finite, got {$component}.");
            }
        }
    }

    public static function identity(): self
    {
        return new self(1.0, 0.0, 0.0, 1.0, 0.0, 0.0);
    }

    public static function translation(float $x, float $y): self
    {
        return new self(1.0, 0.0, 0.0, 1.0, $x, $y);
    }

    public static function scaling(float $x, float $y): self
    {
        return new self($x, 0.0, 0.0, $y, 0.0, 0.0);
    }

    /** Turns +x towards +y: clockwise on a surface whose y runs down. */
    public static function rotation(float $radians): self
    {
        $cos = cos($radians);
        $sin = sin($radians);

        return new self($cos, $sin, -$sin, $cos, 0.0, 0.0);
    }

    /** This transform after $first: the product maps a point through $first, then through this. */
    public function multiply(self $first): self
    {
        return new self(
            $this->a * $first->a + $this->c * $first->b,
            $this->b * $first->a + $this->d * $first->b,
            $this->a * $first->c + $this->c * $first->d,
            $this->b * $first->c + $this->d * $first->d,
            $this->a * $first->e + $this->c * $first->f + $this->e,
            $this->b * $first->e + $this->d * $first->f + $this->f,
        );
    }

    /** @return array{float, float} */
    public function apply(float $x, float $y): array
    {
        return [$this->a * $x + $this->c * $y + $this->e, $this->b * $x + $this->d * $y + $this->f];
    }

    public function determinant(): float
    {
        return $this->a * $this->d - $this->b * $this->c;
    }

    /** Null when the transform flattens the plane, or its inverse is not finite. */
    public function inverse(): ?self
    {
        $det = $this->determinant();
        if ($det == 0.0) {
            return null;
        }

        $inverse = [
            $this->d / $det,
            -$this->b / $det,
            -$this->c / $det,
            $this->a / $det,
            ($this->c * $this->f - $this->d * $this->e) / $det,
            ($this->b * $this->e - $this->a * $this->f) / $det,
        ];
        foreach ($inverse as $component) {
            if (! is_finite($component)) {
                return null;
            }
        }

        return new self(...$inverse);
    }

    /** True when horizontal stays horizontal and vertical stays vertical: translation and scale only. */
    public function isAxisAligned(): bool
    {
        return $this->b == 0.0 && $this->c == 0.0;
    }

    /** @return array{float, float, float, float, float, float} a, b, c, d, e, f */
    public function toArray(): array
    {
        return [$this->a, $this->b, $this->c, $this->d, $this->e, $this->f];
    }
}
