<?php

namespace Surface\Drawing\Concerns;

use Closure;
use Surface\Contracts\Drawing\Drawing2D;
use Surface\Contracts\Drawing\Executor;
use Surface\Contracts\Drawing\Frame;
use Surface\Contracts\NativeWindows\Views\Color;
use Surface\Drawing\Painter;

/**
 * The frame loop every DrawTarget shares: one hook, the clear colour,
 * continuous or on-demand frames, the Frame clock, and the rule that a begun
 * frame is always ended. The using class answers what only it knows — its
 * executor, its size in points, its backing scale, whether it is visible —
 * and calls bootFrames() from its constructor.
 */
trait RunsFrames
{
    protected Painter $painter;

    protected ?Closure $on_draw = null;

    protected Color $clear_color;

    protected bool $continuous = true;

    protected bool $pending_redraw = false;

    protected int $frame_index = 0;

    protected ?float $first_frame_at = null;

    protected ?float $last_frame_at = null;

    abstract public function executor(): Executor;

    /** @return array{int, int} Target size in points. */
    abstract protected function frameSize(): array;

    abstract protected function frameScale(): float;

    abstract protected function frameVisible(): bool;

    protected function bootFrames(Executor $executor): void
    {
        $this->painter = new Painter($executor);
        $this->clear_color = new Color(0.0, 0.0, 0.0, 1.0);
    }

    public function drawing(): Drawing2D
    {
        return $this->painter;
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

    /**
     * Mark a frame wanted and let the class queue it natively — the door a
     * self-driving target (GtkGLArea) uses; the default queues nothing.
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
     * One frame now. Skipped when not visible, hookless, or neither
     * continuous nor pending; skipped when the executor has no drawable. A
     * hook exception propagates — sketch code, sketch problem — but the frame
     * is ended first so the engine is never left mid-frame.
     */
    public function renderFrame(): bool
    {
        if (! $this->frameVisible() || is_null($this->on_draw)) {
            return false;
        }

        if (! $this->continuous && ! $this->pending_redraw) {
            return false;
        }

        $executor = $this->executor();
        if (! $executor->beginFrame($this->clear_color)) {
            return false;
        }

        $this->pending_redraw = false;
        [$width, $height] = $this->frameSize();
        $scale = $this->frameScale();
        $now = microtime(true);
        $this->first_frame_at ??= $now;

        $frame = new Frame(
            index: $this->frame_index,
            time: $now - $this->first_frame_at,
            delta: is_null($this->last_frame_at) ? 0.0 : $now - $this->last_frame_at,
            width: $width,
            height: $height,
            scale: $scale,
        );
        $this->last_frame_at = $now;
        $this->frame_index++;

        try {
            $this->painter->begin($width, $height, $scale);
            ($this->on_draw)($this->painter, $frame);
            $this->painter->flush();
        } finally {
            $this->painter->reset();
            $executor->endFrame();
        }

        return true;
    }

    /** Door for a self-driving target to queue a native render. Default: nothing. */
    protected function queueNativeFrame(): void {}
}
