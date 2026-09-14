<?php

namespace Surface\Contracts\Drawing;

/** What an engine can honestly do today. The Painter degrades on false, never lies. */
final readonly class ExecutorCapabilities
{
    public function __construct(
        public bool $blending,
        public bool $depth,
        public bool $instancing,
        public bool $readback,
        public int $max_texture_size,
    ) {}
}
