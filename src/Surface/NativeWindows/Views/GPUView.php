<?php

namespace Surface\NativeWindows\Views;

use Surface\Contracts\Drawing\Executor;
use Surface\Contracts\Drawing\GPUEngine;
use Surface\Contracts\NativeWindows\Views\Color;
use Surface\Contracts\NativeWindows\Views\OSGPUView;
use Surface\Drawing\Concerns\RunsFrames;
use Surface\NativeWindows\Windowable;

/**
 * A GPU region conjured into a window. The frame loop is RunsFrames, shared
 * with staged windows; this class adds the View half and release-before-destroy.
 * Twins fill applyFrame (must call executor->resize() in pixels and refresh the
 * scale), destroyNative (drop the native), applyVisible. measure() answers the
 * frame; applyBackground() is ignored — the engine paints the region.
 */
abstract class GPUView extends View implements OSGPUView
{
    use RunsFrames;

    public function __construct(
        string $name,
        Windowable $window,
        protected GPUEngine $gpu_engine,
        protected Executor $executor,
        protected float $scale = 1.0,
    ) {
        parent::__construct($name, $window);
        $this->bootFrames($executor);
    }

    public function engine(): GPUEngine
    {
        return $this->gpu_engine;
    }

    public function executor(): Executor
    {
        return $this->executor;
    }

    public function scale(): float
    {
        return $this->scale;
    }

    public function drawableSize(): array
    {
        return $this->executor->drawableSize();
    }

    /** Release the engine's resources first, then the native, then the name. */
    public function remove(): void
    {
        $this->executor->release();
        parent::remove();
    }

    protected function frameSize(): array
    {
        return [$this->width, $this->height];
    }

    protected function frameScale(): float
    {
        return $this->scale;
    }

    protected function frameVisible(): bool
    {
        return $this->visible;
    }

    /** A GPU region has no natural size; it is whatever it was placed at. */
    protected function measure(): array
    {
        return [$this->width, $this->height];
    }

    /** Ignored, stated: the engine paints every pixel of the region. */
    protected function applyBackground(Color $color): void {}

    /** Twins refresh this from their window in applyFrame(). */
    protected function setScale(float $scale): void
    {
        $this->scale = $scale;
    }
}
