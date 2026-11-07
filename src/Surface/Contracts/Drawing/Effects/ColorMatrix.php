<?php

namespace Surface\Contracts\Drawing\Effects;

use Surface\Contracts\Drawing\DrawingException;

/**
 * SVG's feColorMatrix, CSS saturate(), brightness() and the like, CoreImage's CIColorMatrix: a 4 x 5 matrix on the
 * straight (not premultiplied) colour, in 0..1 sRGB values: R' = m0 R + m1 G + m2 B + m3 A + m4, and so for G', B', A'.
 */
final readonly class ColorMatrix extends FilterStep
{
    /** @param list<float> $values Twenty numbers, row by row. */
    public function __construct(public array $values, public ?string $in = null, public ?string $result = null)
    {
        if (count($values) !== 20 || ! array_is_list($values)) {
            throw new DrawingException('A colour matrix is twenty numbers, four rows of five.');
        }
        foreach ($values as $value) {
            if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
                throw new DrawingException('A colour matrix takes finite numbers.');
            }
        }
    }

    /** CSS saturate() and SVG's type="saturate": 0 is grey, 1 leaves the colours, above 1 strengthens them. */
    public static function saturate(float $amount, ?string $in = null, ?string $result = null): self
    {
        $s = $amount;

        return new self([
            0.213 + 0.787 * $s, 0.715 - 0.715 * $s, 0.072 - 0.072 * $s, 0, 0,
            0.213 - 0.213 * $s, 0.715 + 0.285 * $s, 0.072 - 0.072 * $s, 0, 0,
            0.213 - 0.213 * $s, 0.715 - 0.715 * $s, 0.072 + 0.928 * $s, 0, 0,
            0, 0, 0, 1, 0,
        ], $in, $result);
    }

    /** SVG's type="hueRotate", the angle in degrees. */
    public static function hueRotate(float $degrees, ?string $in = null, ?string $result = null): self
    {
        $c = cos(deg2rad($degrees));
        $s = sin(deg2rad($degrees));

        return new self([
            0.213 + $c * 0.787 - $s * 0.213, 0.715 - $c * 0.715 - $s * 0.715, 0.072 - $c * 0.072 + $s * 0.928, 0, 0,
            0.213 - $c * 0.213 + $s * 0.143, 0.715 + $c * 0.285 + $s * 0.140, 0.072 - $c * 0.072 - $s * 0.283, 0, 0,
            0.213 - $c * 0.213 - $s * 0.787, 0.715 - $c * 0.715 + $s * 0.715, 0.072 + $c * 0.928 + $s * 0.072, 0, 0,
            0, 0, 0, 1, 0,
        ], $in, $result);
    }

    /** SVG's type="luminanceToAlpha": the colour goes black, its luminance becomes the alpha. */
    public static function luminanceToAlpha(?string $in = null, ?string $result = null): self
    {
        return new self([0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0.2125, 0.7154, 0.0721, 0, 0], $in, $result);
    }

    /** CSS brightness(): the colours scaled (a scale of 1 leaves them), added to by $offset (0..1). */
    public static function brightness(float $scale, float $offset = 0.0, ?string $in = null, ?string $result = null): self
    {
        return new self([$scale, 0, 0, 0, $offset, 0, $scale, 0, 0, $offset, 0, 0, $scale, 0, $offset, 0, 0, 0, 1, 0], $in, $result);
    }

    public function reads(): array
    {
        return [$this->in ?? ''];
    }

    public function named(): ?string
    {
        return $this->result;
    }
}
