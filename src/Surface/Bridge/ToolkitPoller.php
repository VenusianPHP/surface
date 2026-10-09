<?php

namespace Surface\Bridge;

use Voyager\IOPools\Resources\Pollable;

/**
 * The loop resource for a toolkit that cannot sleep on a descriptor (SDL 3,
 * GLFW): ticked at the loop's pace, it drains the toolkit's queue without
 * blocking and sends the latest-wins mail. Never crowned: the loop sleeps in
 * its own waiter.
 */
class ToolkitPoller extends Pollable
{
    public function __construct(
        protected readonly BridgedToolkitSession $session
    ) {}

    public function tick(): void
    {
        $this->session->pump(0);
        $this->session->flushLatest();
    }
}
