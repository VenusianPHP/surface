<?php

namespace Surface\Stage;

use Surface\Contracts\Drawing\GPUEngineDriver;
use Surface\Contracts\Stage\StageException;
use Surface\Contracts\Stage\StageHost;
use Surface\Contracts\Stage\StageSession as StageSessionContract;

/**
 * Shared policy for every window maker: the bridge session's two flags
 * (engine started once, connection cycles freely) with a lazy start — a
 * host's engine does not start because the container built the session.
 * Host packages fill the hooks.
 */
abstract class StageSession implements StageSessionContract
{
    protected bool $connected = false;

    protected bool $initialized = false;

    abstract public function host(): StageHost;

    abstract public function sharesNativePump(): bool;

    abstract protected function initializeEngine(): void;

    abstract protected function connectToEngine(): void;

    abstract protected function disconnectEngine(): void;

    abstract protected function pumpEngine(int $budget_ms): int;

    /** Mint a hidden stage with the engine attached. Throw StageException::unsupported() for a kind this host cannot mint. */
    abstract protected function mintStage(string $name, GPUEngineDriver $engine, int $width, int $height): StagedWindow;

    public function connect(): static
    {
        if ($this->connected) {
            return $this;
        }

        if (! $this->initialized) {
            $this->initializeEngine();
            $this->initialized = true;
        }

        $this->connectToEngine();
        $this->connected = true;

        return $this;
    }

    public function disconnect(): void
    {
        if (! $this->connected) {
            return;
        }

        // Disconnected even when the drain or the engine hook throws.
        try {
            $this->pump(0);
            $this->disconnectEngine();
        } finally {
            $this->connected = false;
        }
    }

    public function connected(): bool
    {
        return $this->connected;
    }

    public function pump(int $budget_ms = 0): int
    {
        return $this->connected ? $this->pumpEngine($budget_ms) : 0;
    }

    public function open(string $name, GPUEngineDriver $engine, int $width, int $height): StagedWindow
    {
        if (! $this->connected) {
            throw StageException::notConnected($this->host());
        }

        return $this->mintStage($name, $engine, $width, $height);
    }
}
