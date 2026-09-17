<?php

namespace Surface\Contracts\Drawing;

/** A DrawTarget an engine package draws through an Executor — GPUView and StagedWindow. */
interface GPUDrawTarget extends DrawTarget
{
    public function engine(): GPUEngine;

    public function executor(): Executor;
}
