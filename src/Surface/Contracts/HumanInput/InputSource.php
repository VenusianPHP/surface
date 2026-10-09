<?php

namespace Surface\Contracts\HumanInput;

/**
 * Anything HumanInput polls for devices: a toolkit session's input engine or the pad source.
 * poll() applies what arrived since the last poll and never clears an edge; settle()
 * clears edges, text, motion and wheel once a frame has read them. Neither waits.
 */
interface InputSource
{
    /** Start listening. Idempotent. */
    public function connect(): static;

    public function disconnect(): void;

    public function connected(): bool;

    /** Clear every device's edges, text, motion and wheel: the frame that read them is over. */
    public function settle(): void;

    /** Apply what arrived since the last poll. Edges add up until settle(). */
    public function poll(): void;
}
