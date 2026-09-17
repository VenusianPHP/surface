<?php

namespace Surface\Contracts\Drawing;

/** What an engine binds behind its container alias (`cpu.dirty`, ...). */
interface CPUEngineDriver
{
    public function engine(): CPUEngine;

    public function attach(CPUHost $host): CPUDrawTarget;
}
