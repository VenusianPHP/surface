<?php

namespace Surface\Contracts\Stage;

use Surface\Contracts\Drawing\DrawTarget;
use Voyager\Contracts\IOPools\PoolPump;

/**
 * A whole window an engine owns and draws every pixel of. No native controls
 * live in it. Minted hidden; show() presents. close() is terminal. Engine-free:
 * GPUStagedWindow adds the executor, CPUStagedWindow the canvas.
 */
interface StagedWindow extends DrawTarget
{
    public function name(): string;

    public function title(): string;

    public function setTitle(string $title): static;

    /** @return array{int, int} Size in points. */
    public function size(): array;

    public function scale(): float;

    public function show(): static;

    /** Release the engine, destroy the native window, announce once. Idempotent. */
    public function close(): void;

    public function isOpen(): bool;

    /** Where stage mail (resized, closed) is pushed. */
    public function setPool(PoolPump $pool): static;
}
