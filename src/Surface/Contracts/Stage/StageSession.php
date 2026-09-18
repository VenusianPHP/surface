<?php

namespace Surface\Contracts\Stage;

use Surface\Contracts\Drawing\CPUEngineDriver;
use Surface\Contracts\Drawing\CPUHost;
use Surface\Contracts\Drawing\GPUEngineDriver;

/**
 * One window maker's link to PHPland: the same connect / disconnect / pump
 * lifecycle as the OS bridge, plus the factory that mints whole engine-owned
 * windows. Engine start is lazy — it happens at the first connect().
 */
interface StageSession
{
    public function host(): StageHost;

    public function connect(): static;

    public function disconnect(): void;

    public function connected(): bool;

    /** Advance the host's event loop; answers units of work dispatched. Never blocks for this host's sake. */
    public function pump(int $budget_ms = 0): int;

    /** True when this host's events arrive through the native bridge's pump (AppKit): the stage resource then skips its own pump. */
    public function sharesNativePump(): bool;

    /**
     * True when this host must be the one draining the OS event queue (SDL on
     * macOS reads keys only inside its own pump): the os resource then skips
     * its native pump and hands this host the tick's idle wait.
     */
    public function ownsNativePump(): bool;

    /**
     * Mint a stage, hidden, with the engine attached to it.
     * @throws StageException When disconnected, when this host cannot give the engine its surface kind, or when the
     *                         engine's attach() fails (StageException::attachFailed, the engine's exception as previous).
     */
    public function open(string $name, GPUEngineDriver $engine, int $width, int $height): GPUStagedWindow;

    /**
     * Mint a CPU stage, hidden: a window that presents $canvas, scaled by $fit.
     * The canvas keeps the size it was minted at.
     *
     * @throws StageException When disconnected, or when this host cannot present a CPU canvas
     *                        (StageException::cpuUnsupported).
     */
    public function openCPU(string $name, CPUEngineDriver $engine, CPUHost $canvas, int $width, int $height, StageFit $fit): CPUStagedWindow;
}
