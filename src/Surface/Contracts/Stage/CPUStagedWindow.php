<?php

namespace Surface\Contracts\Stage;

use Surface\Contracts\Drawing\CPUDrawTarget;
use Surface\Contracts\Drawing\DrawsWith;

/**
 * A stage that presents a CPU canvas's pixels. The canvas is fixed at the size
 * it was minted; the window scales it by fit(). size()/scale() are the window,
 * canvasSize()/drawableSize()/flush() are the canvas — so the same object can
 * feed this window and a panel in one tick.
 *
 * The window presents whatever the canvas holds, so which canvas is DrawsWith:
 * swapping the rasteriser changes nothing the window knows about.
 */
interface CPUStagedWindow extends StagedWindow, CPUDrawTarget, DrawsWith
{
    public function fit(): StageFit;

    /** @return array{int, int} The canvas's size in pixels. */
    public function canvasSize(): array;
}
