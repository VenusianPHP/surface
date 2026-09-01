<?php

namespace Surface\Bridge;

use Surface\Contracts\Bridge\BridgedOSSession as OSSessionContract;

/**
 * Shared lifecycle for every OS windowing engine.
 *
 * Two flags, because two different things are being guarded. $initialized covers
 * the once-per-process engine start that cannot be repeated or undone, and runs
 * at construction. $connected covers the part that cycles freely on top of it.
 *
 * Engine packages supply the four hooks and inherit every guard here.
 */
abstract class BridgedOSSession implements OSSessionContract
{
    /**
     * Flag indicating the bridge is connected
     * @var bool
     */
    protected bool $connected = false;

    /**
     * Flag indicating initialization
     * @var bool
     */
    protected bool $initialized = false;

    public function __construct() {
        $this->bootstrap();
    }

    /**
     * Start the engine. Called at most once per process, never repeated or undone.
     * @return void
     */
    abstract protected function initializeEngine(): void;

    /**
     * Make the running engine present to the OS. Cyclable.
     * @return void
     */
    abstract protected function connectToEngine(): void;

    /**
     * Withdraw the running engine from the OS. Cyclable, and the inverse of connectToEngine().
     * @return void
     */
    abstract protected function disconnectEngine(): void;

    /**
     * Advance the engine's own event loop.
     * @param int $budget_ms Milliseconds the engine may spend. Zero drains without waiting.
     * @return int Units of work the engine dispatched.
     */
    abstract protected function pumpEngine(int $budget_ms): int;

    /**
     * Bring the session up, initialising the engine first if it has never run.
     * @return $this
     */
    public function connect(): static
    {
        if ($this->connected) return $this;

        $this->initialize();
        $this->connectToEngine();
        $this->connected = true;

        return $this;
    }

    /**
     * Take the session down, draining the loop so the engine sees the last of our work.
     * @return void
     */
    public function disconnect(): void
    {
        if (! $this->connected) return;

        $this->pump();
        $this->disconnectEngine();
        $this->connected = false;
    }

    /**
     * Report whether the session is currently connected.
     * @return bool
     */
    public function connected(): bool
    {
        return $this->connected;
    }

    /**
     * Advance the engine's event loop, or do nothing at all while disconnected.
     *
     * Answers zero rather than throwing when there is nothing to pump, so a sketch
     * loop that outlives its bridge cannot blow up mid-tick.
     *
     * @param int $budget_ms Milliseconds the engine may spend. Zero drains without waiting.
     * @return int Units of work the engine dispatched. Always zero while disconnected.
     */
    public function pump(int $budget_ms = 0): int
    {
        return $this->connected ? $this->pumpEngine($budget_ms) : 0;
    }

    /**
     * @param array $args
     * @return $this
     */
    public function setMenuBar(string $menu_name, array $args): static
    {
        return $this;
    }

    /**
     * Start the engine on first use, and never again for the life of the process.
     * @return void
     */
    protected function initialize(): void
    {
        if (! $this->initialized) {
            $this->initializeEngine();
            $this->initialized = true;
        }
    }

    /**
     * Prepare the session at construction, leaving it initialised but not yet connected.
     * @return void
     */
    protected function bootstrap(): void
    {
        $this->initialize();
    }


}
