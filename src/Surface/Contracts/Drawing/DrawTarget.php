<?php

namespace Surface\Contracts\Drawing;

use Surface\Contracts\NativeWindows\Views\Color;

/**
 * Anything that runs a draw hook and presents — engine-free. GPUDrawTarget adds
 * the engine and executor; CPUDrawTarget adds flush, damage and rgba8.
 */
interface DrawTarget
{
    public function drawing(): Drawing2D;

    /** One hook, replace not stack: fn(Drawing2D $g, Frame $frame): void */
    public function onDraw(callable $hook): static;

    /**
     * Per-frame engines (GPU, nframes, paged) apply it before the hook runs;
     * preserving engines (dirty, full, epaper) apply it once at attach and on
     * clear(). Default opaque black.
     */
    public function setClearColor(Color $color): static;

    /** Default true once a hook is set. */
    public function setContinuous(bool $continuous): static;

    /** One frame on the next tick. */
    public function redraw(): static;

    /** @return array{int, int} pixels */
    public function drawableSize(): array;

    /** Run one frame now; false when skipped. */
    public function renderFrame(): bool;
}
