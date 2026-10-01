<?php

namespace Surface\Contracts\Bridge;

interface BridgedToolkitSession
{
    /**
     * Bring the session up, initialising the engine first if it has never run.
     * @return static
     */
    public function connect(): static;

    /**
     * Take the session down, draining anything the engine still has queued.
     * @return void
     */
    public function disconnect(): void;

    /**
     * Report whether the session is currently connected.
     * @return bool
     */
    public function connected(): bool;
}