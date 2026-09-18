<?php

namespace Surface\Contracts\Fonts;

/** One glyph's metrics: where its bytes start and how it sits on the pen. Pixels. */
final readonly class Glyph
{
    public function __construct(
        public int $bitmap_offset,
        public int $width,
        public int $height,
        public int $x_advance,
        public int $x_offset,
        public int $y_offset,
    ) {}
}
