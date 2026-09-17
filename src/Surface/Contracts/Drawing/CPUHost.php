<?php

namespace Surface\Contracts\Drawing;

use Surface\Contracts\Framebuffers\FormatSpec;

/** What a caller hands a CPU engine at attach: size, the host byte format, and the two knobs only some engines read. */
final readonly class CPUHost
{
    public function __construct(
        public int $width,
        public int $height,
        public FormatSpec $format,
        public int $frames = 2,
        public int $page_rows = 8,
    ) {}
}
