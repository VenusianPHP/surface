<?php

namespace Surface\Contracts\Drawing;

/** One tick of a DrawTarget: frame index, seconds since the first frame, seconds since the last, target size in points, backing scale. */
final readonly class Frame
{
    public function __construct(
        public int $index,
        public float $time,
        public float $delta,
        public int $width,
        public int $height,
        public float $scale,
    ) {}
}
