<?php

namespace Surface\Contracts\EmbeddedDisplays;

use Surface\Contracts\Drawing\CPUDrawTarget;
use Surface\Contracts\Drawing\DrawsWith;
use Voyager\Contracts\IOPools\PoolPump;

/**
 * A CPU canvas whose pixels go to an IC display panel. renderFrame() runs the
 * canvas, then present() sends what changed. Engine-free and GPIO-free: the
 * panel itself is reached through the concrete class, never this contract.
 *
 * Which canvas is DrawsWith: the panel's size and format are the panel's, and
 * any rasteriser that answers them may draw — a CPU engine, or a GPU engine
 * running headless.
 */
interface EmbeddedDisplay extends CPUDrawTarget, DrawsWith
{
    public function name(): string;

    /** The whole frame the first time and after show(), damage after that, then a refresh where the panel needs one. */
    public function present(): void;

    /** Panel output on, then the whole frame so the panel matches the canvas. Throws when the panel cannot switch. */
    public function show(): static;

    /** Panel output off; frames are skipped while hidden. Throws when the panel cannot switch. */
    public function hide(): static;

    public function isVisible(): bool;

    /** Whether show() and hide() are available on this panel. */
    public function switchable(): bool;

    /** Terminal and idempotent: output off where the panel can, then the display leaves its manager. The chip and its bus stay the sketch's to close. */
    public function close(): void;

    public function isOpen(): bool;

    /** A panel write threw. The display sends nothing more; detach and attach again to recover. */
    public function faulted(): bool;

    public function fault(): ?\Throwable;

    /** Where the one fault announcement is pushed. */
    public function setPool(PoolPump $pool): static;
}
