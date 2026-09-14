<?php

namespace Surface\Contracts\Drawing;

/**
 * A 4x4 float matrix, column-major, the one matrix an executor receives per
 * draw. The Painter uses it for projection only; the sketch's 2D stack is
 * folded into the vertices before they reach an engine.
 *
 * m[col][row]. apply() treats (x, y) as (x, y, 0, 1).
 */
final readonly class Transform
{
    /** @param list<list<float>> $m four columns of four rows */
    private function __construct(private array $m) {}

    public static function identity(): self
    {
        return new self([
            [1.0, 0.0, 0.0, 0.0],
            [0.0, 1.0, 0.0, 0.0],
            [0.0, 0.0, 1.0, 0.0],
            [0.0, 0.0, 0.0, 1.0],
        ]);
    }

    /**
     * Top-left pixel space of a $width x $height target to canonical clip
     * space: x in [-1, 1] left to right, y in [-1, 1] BOTTOM to top. Engines
     * that want y down pre-multiply their own flip.
     */
    public static function orthographic(int $width, int $height): self
    {
        $w = max(1, $width);
        $h = max(1, $height);

        return new self([
            [2.0 / $w, 0.0, 0.0, 0.0],
            [0.0, -2.0 / $h, 0.0, 0.0],
            [0.0, 0.0, 1.0, 0.0],
            [-1.0, 1.0, 0.0, 1.0],
        ]);
    }

    public function translate(float $dx, float $dy): self
    {
        return $this->multiply(new self([
            [1.0, 0.0, 0.0, 0.0],
            [0.0, 1.0, 0.0, 0.0],
            [0.0, 0.0, 1.0, 0.0],
            [$dx, $dy, 0.0, 1.0],
        ]));
    }

    public function rotate(float $radians): self
    {
        $c = cos($radians);
        $s = sin($radians);

        return $this->multiply(new self([
            [$c, $s, 0.0, 0.0],
            [-$s, $c, 0.0, 0.0],
            [0.0, 0.0, 1.0, 0.0],
            [0.0, 0.0, 0.0, 1.0],
        ]));
    }

    public function scale(float $sx, float $sy): self
    {
        return $this->multiply(new self([
            [$sx, 0.0, 0.0, 0.0],
            [0.0, $sy, 0.0, 0.0],
            [0.0, 0.0, 1.0, 0.0],
            [0.0, 0.0, 0.0, 1.0],
        ]));
    }

    /** this × other — other is applied to a point first. */
    public function multiply(Transform $other): self
    {
        $out = [];
        for ($col = 0; $col < 4; $col++) {
            for ($row = 0; $row < 4; $row++) {
                $sum = 0.0;
                for ($k = 0; $k < 4; $k++) {
                    $sum += $this->m[$k][$row] * $other->m[$col][$k];
                }
                $out[$col][$row] = $sum;
            }
        }

        return new self($out);
    }

    /** @return array{float, float} */
    public function apply(float $x, float $y): array
    {
        $m = $this->m;

        return [
            $m[0][0] * $x + $m[1][0] * $y + $m[3][0],
            $m[0][1] * $x + $m[1][1] * $y + $m[3][1],
        ];
    }

    /** Sixteen little-endian floats, column-major — one `float4x4` on every engine. */
    public function toPacked(): string
    {
        $flat = [];
        foreach ($this->m as $column) {
            foreach ($column as $value) {
                $flat[] = $value;
            }
        }

        return pack('g16', ...$flat);
    }
}
