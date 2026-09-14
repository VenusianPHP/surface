<?php

namespace Surface\Contracts\Drawing;

use Surface\Contracts\NativeWindows\Views\Color;

/** Anything that can be drawn into and presented — GPUView now, Stage and EmbeddedDisplay later. */
interface DrawTarget
{
    public function engine(): GPUEngine;

    public function drawing(): Drawing2D;

    public function executor(): Executor;

    /** One hook, replace not stack: fn(Drawing2D $g, Frame $frame): void */
    public function onDraw(callable $hook): static;

    /** Applied by the engine's load action before the hook runs. Default opaque black. */
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
