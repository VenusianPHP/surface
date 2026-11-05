<?php

namespace Surface\NutsAndBolts;

/**
 * A two-stop linear gradient across a shape's box: from at one side, to at the other.
 * 0 degrees runs left to right, 90 top to bottom (y grows down).
 */
final readonly class LinearGradient
{
    public function __construct(
        public Color $from,
        public Color $to,
        public float $angleDegrees = 90.0,
    ) {}

    /** The colour at $t, 0 (from) .. 1 (to). */
    public function at(float $t): Color
    {
        $t = min(1.0, max(0.0, $t));

        return new Color(
            $this->from->red + ($this->to->red - $this->from->red) * $t,
            $this->from->green + ($this->to->green - $this->from->green) * $t,
            $this->from->blue + ($this->to->blue - $this->from->blue) * $t,
            $this->from->alpha + ($this->to->alpha - $this->from->alpha) * $t,
        );
    }
}
