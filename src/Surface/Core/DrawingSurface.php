<?php

namespace Surface\Core;

use Surface\Contracts\Drawing\OutputTarget;
use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Core\DrawingSurface as SurfaceContract;

readonly class DrawingSurface implements SurfaceContract
{
    public function __construct(
        public OutputTarget $target,
        public RenderingEngine $engine,
    ) {}

    /**
     * @todo - api go burr.
     */
}