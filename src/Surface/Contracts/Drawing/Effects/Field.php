<?php

namespace Surface\Contracts\Drawing\Effects;

use Surface\Contracts\Drawing\DrawingException;

/**
 * An image made from the position of each pixel on the surface, as feFlood makes one from nothing and feTurbulence
 * from the position: opaque, 8 bits a channel, and nothing read. The centre of the pixel at column x, row y of the
 * surface is (x + ½, y + ½).
 *
 * ROUNDED_BOX: with d the distance from that point to the rounded box (negative inside) and (nx, ny) the unit direction
 * pointing out of it, red is (d − from) / (to − from) held to 0..1, green ½ + ½ nx, blue ½ + ½ ny. INSIDE: 255 where d is below 0.
 * RAMP: red, green and blue are (y + ½ − from) / (to − from) held to 0..1. Each channel is rounded to nearest: floor(v × 255 + ½).
 *
 * The box is [x0, y0, x1, y1] on the surface; the radii [top left, top right, bottom right, bottom left] each held to half
 * the box's width and height. A chain's later steps (matrix, composite, table) work on what this makes.
 */
final readonly class Field extends FilterStep
{
    /**
     * @param  array{float, float, float, float}|null  $box
     * @param  array{float, float, float, float}  $radii
     *
     * @throws DrawingException When a box is missing, a number is not finite, or the window is empty.
     */
    public function __construct(
        public FieldKind $kind,
        public ?array $box = null,
        public array $radii = [0.0, 0.0, 0.0, 0.0],
        public float $from = 0.0,
        public float $to = 1.0,
        public ?string $result = null,
    ) {
        // a number under 1e-30 is flushed to 0 by a GPU's float32 decode: a window that small is no window
        if (! is_finite($from) || ! is_finite($to) || ($kind !== FieldKind::INSIDE && abs($to - $from) < 1e-30)) {
            throw new DrawingException('A field window takes two finite numbers that differ by 1e-30 or more.');
        }
        if ($kind !== FieldKind::RAMP) {
            if ($box === null || count($box) !== 4 || count($radii) !== 4 || ! array_is_list($box) || ! array_is_list($radii)) {
                throw new DrawingException('A field of a box takes [x0, y0, x1, y1] and four radii.');
            }
            foreach ([...$box, ...$radii] as $v) {
                if ((! is_int($v) && ! is_float($v)) || ! is_finite((float) $v)) {
                    throw new DrawingException('A field of a box takes finite numbers.');
                }
            }
        }
    }

    /** @param array{float, float, float, float} $box @param array{float, float, float, float} $radii */
    public static function roundedBox(array $box, array $radii, float $from, float $to, ?string $result = null): self
    {
        return new self(FieldKind::ROUNDED_BOX, $box, $radii, $from, $to, $result);
    }

    /** @param array{float, float, float, float} $box @param array{float, float, float, float} $radii */
    public static function inside(array $box, array $radii, ?string $result = null): self
    {
        return new self(FieldKind::INSIDE, $box, $radii, 0.0, 1.0, $result);
    }

    public static function ramp(float $from, float $to, ?string $result = null): self
    {
        return new self(FieldKind::RAMP, null, [0.0, 0.0, 0.0, 0.0], $from, $to, $result);
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
