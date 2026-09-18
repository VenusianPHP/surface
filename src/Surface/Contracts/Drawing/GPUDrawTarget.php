<?php

namespace Surface\Contracts\Drawing;

/** A DrawTarget an engine package draws through an Executor — GPUView and GPUStagedWindow. */
interface GPUDrawTarget extends DrawTarget
{
    public function engine(): GPUEngine;

    public function executor(): Executor;
}
