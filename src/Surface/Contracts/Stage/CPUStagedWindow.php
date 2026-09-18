<?php

namespace Surface\Contracts\Stage;

use Surface\Contracts\Drawing\CPUDrawTarget;

/**
 * A stage that presents a CPU canvas's pixels. The canvas is fixed at the size
 * it was minted; the window scales it by fit(). size()/scale() are the window,
 * canvasSize()/drawableSize()/flush() are the canvas — so the same object can
 * feed this window and a panel in one tick.
 */
interface CPUStagedWindow extends StagedWindow, CPUDrawTarget
{
    public function fit(): StageFit;

    /** @return array{int, int} The canvas's size in pixels. */
    public function canvasSize(): array;
}
