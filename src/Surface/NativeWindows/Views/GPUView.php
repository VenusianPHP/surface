<?php

namespace Surface\NativeWindows\Views;

use Closure;
use Surface\Contracts\Drawing\Drawing2D;
use Surface\Contracts\Drawing\Executor;
use Surface\Contracts\Drawing\Frame;
use Surface\Contracts\Drawing\GPUEngine;
use Surface\Contracts\NativeWindows\Views\Color;
use Surface\Contracts\NativeWindows\Views\OSGPUView;
use Surface\Drawing\Painter;
use Surface\NativeWindows\Windowable;

/**
 * A GPU region conjured into a window. Owns the frame-loop policy: skip
 * rules, the Frame clock, hook-error safety, release-before-destroy. Twins
 * fill applyFrame (must call executor->resize() in pixels and refresh the
 * scale), destroyNative (drop the native), applyVisible. measure() answers
 * the frame; applyBackground() is ignored — the engine paints the region.
 */
abstract class GPUView extends View implements OSGPUView
{
    protected Painter $painter;

    protected ?Closure $on_draw = null;

    protected Color $clear_color;

    protected bool $continuous = true;

    protected bool $pending_redraw = false;

    protected int $frame_index = 0;

    protected ?float $first_frame_at = null;

    protected ?float $last_frame_at = null;

    public function __construct(
        string $name,
        Windowable $window,
        protected GPUEngine $gpu_engine,
        protected Executor $executor,
        protected float $scale = 1.0,
    ) {
        parent::__construct($name, $window);
        $this->painter = new Painter($executor);
        $this->clear_color = new Color(0.0, 0.0, 0.0, 1.0);
    }

    public function engine(): GPUEngine
    {
        return $this->gpu_engine;
    }

    public function drawing(): Drawing2D
    {
        return $this->painter;
    }

    public function executor(): Executor
    {
        return $this->executor;
    }

    public function scale(): float
    {
        return $this->scale;
    }

    public function onDraw(callable $hook): static
    {
        $this->on_draw = $hook(...);

        return $this;
    }

    public function setClearColor(Color $color): static
    {
        $this->clear_color = $color;

        return $this;
    }

    public function setContinuous(bool $continuous): static
    {
        $this->continuous = $continuous;

        return $this;
    }

    public function redraw(): static
    {
        $this->requestFrame();

        return $this;
    }

    public function drawableSize(): array
    {
        return $this->executor->drawableSize();
    }

    /**
     * Mark a frame wanted and let the twin queue it natively — the door a
     * self-driving twin (GtkGLArea) uses; the default twin does nothing.
     */
    public function requestFrame(): void
    {
        $this->pending_redraw = true;
        $this->queueNativeFrame();
    }

    /** True when the engine calls renderFrame() from its own render signal. */
    public function drivesOwnFrames(): bool
    {
        return false;
    }

    /**
     * One frame now. Skipped when hidden, hookless, or neither continuous nor
     * pending; skipped when the executor has no drawable. A hook exception
     * propagates — sketch code, sketch problem — but the frame is ended first
     * so the engine is never left mid-frame.
     */
    public function renderFrame(): bool
    {
        if (! $this->visible || is_null($this->on_draw)) {
            return false;
        }
        if (! $this->continuous && ! $this->pending_redraw) {
            return false;
        }

        if (! $this->executor->beginFrame($this->clear_color)) {
            return false;
        }
        $this->pending_redraw = false;

        $now = microtime(true);
        $this->first_frame_at ??= $now;
        $frame = new Frame(
            index: $this->frame_index,
            time: $now - $this->first_frame_at,
            delta: is_null($this->last_frame_at) ? 0.0 : $now - $this->last_frame_at,
            width: $this->width,
            height: $this->height,
            scale: $this->scale,
        );
        $this->last_frame_at = $now;
        $this->frame_index++;

        try {
            $this->painter->begin($this->width, $this->height, $this->scale);
            ($this->on_draw)($this->painter, $frame);
            $this->painter->flush();
        } finally {
            $this->painter->reset();
            $this->executor->endFrame();
        }

        return true;
    }

    /** Release the engine's resources first, then the native, then the name. */
    public function remove(): void
    {
        $this->executor->release();
        parent::remove();
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

    /** Door for a self-driving twin to queue a native render. Default: nothing. */
    protected function queueNativeFrame(): void {}
}
