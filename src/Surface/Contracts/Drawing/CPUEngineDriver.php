<?php

namespace Surface\Contracts\Drawing;

/** What an engine package binds behind its container alias (`cpu.phpdafruit`, ...). */
interface CPUEngineDriver
{
    public function engine(): CPUEngine;
}
