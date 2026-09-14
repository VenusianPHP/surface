<?php

namespace Surface\Contracts\Drawing;

/** What an engine package binds behind its container alias (`gpu.metal`, ...). */
interface GPUEngineDriver
{
    public function engine(): GPUEngine;

    /** What the window engine must mint before calling attach(). */
    public function surfaceKind(): SurfaceKind;

    public function attach(GPUHost $host): GPUAttachment;
}
