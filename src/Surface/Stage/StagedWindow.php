<?php

namespace Surface\Stage;

use Surface\Contracts\Drawing\Executor;
use Surface\Contracts\Drawing\GPUEngine;
use Surface\Contracts\Stage\Events\StageClosed;
use Surface\Contracts\Stage\Events\StageResized;
use Surface\Contracts\Stage\StagedWindow as StagedWindowContract;
use Surface\Contracts\Stage\StageException;
use Surface\Drawing\Concerns\RunsFrames;
use Voyager\Contracts\IOPools\PoolPump;

/**
 * Shared policy for every engine-owned window: the frame loop (RunsFrames),
 * the size and scale Surface believes in, change-only resize mail, one close
 * announcement, and release-before-destroy. Minted hidden: no frame runs
 * until show(). Host packages fill three hooks and call the two doors from
 * their pump.
 */
abstract class StagedWindow implements StagedWindowContract
{
    use RunsFrames;

    protected string $title = '';

    protected bool $open = true;

    protected bool $close_announced = false;

    private bool $shown = false;

    protected ?PoolPump $io_pool = null;

    public function __construct(
        public readonly string $name,
        protected GPUEngine $gpu_engine,
        protected Executor $executor,
        protected int $width,
        protected int $height,
        protected float $scale = 1.0,
    ) {
        $this->bootFrames($executor);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function engine(): GPUEngine
    {
        return $this->gpu_engine;
    }

    public function executor(): Executor
    {
        return $this->executor;
    }

    public function drawableSize(): array
    {
        return $this->executor->drawableSize();
    }

    public function title(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->guardOpen();
        $this->title = $title;
        $this->applyTitle($title);

        return $this;
    }

    public function size(): array
    {
        return [$this->width, $this->height];
    }

    public function scale(): float
    {
        return $this->scale;
    }

    public function show(): static
    {
        $this->guardOpen();
        $this->shown = true;
        $this->applyShow();

        return $this;
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    public function setPool(PoolPump $pool): static
    {
        $this->io_pool = $pool;

        return $this;
    }

    /**
     * Door for the host's pump: the window now measures this many points at
     * this backing scale. Change-only; resizes the executor in pixels, asks
     * for a frame (an on-demand stage would otherwise show stale content), and mails.
     */
    public function resized(int $width, int $height, float $scale): void
    {
        if (! $this->open) {
            return;
        }

        if ($width === $this->width && $height === $this->height && $scale === $this->scale) {
            return;
        }

        $this->width = $width;
        $this->height = $height;
        $this->scale = $scale;
        $this->executor->resize((int) round($width * $scale), (int) round($height * $scale));
        $this->requestFrame();
        $this->io_pool?->push(new StageResized($this->name, $width, $height, $scale));
    }

    /**
     * Door for the host's pump: the user asked to close this stage. Mails
     * once; the window stays — the sketch decides whether to close(). With no
     * pool yet nothing is mailed and nothing is spent.
     */
    public function closeRequested(): void
    {
        if ($this->close_announced || is_null($this->io_pool)) {
            return;
        }

        $this->close_announced = true;
        $this->io_pool->push(new StageClosed($this->name));
    }

    /**
     * Terminal: the engine's resources first, then the native, then the one
     * announcement. A release that throws still destroys the native and
     * announces; its exception then propagates.
     */
    public function close(): void
    {
        if (! $this->open) {
            return;
        }

        $this->open = false;

        try {
            $this->executor->release();
        } finally {
            $this->destroyNative();
            $this->closeRequested();
        }
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
        return $this->open && $this->shown;
    }

    protected function guardOpen(): void
    {
        if (! $this->open) {
            throw StageException::closed($this->name);
        }
    }

    abstract protected function applyTitle(string $title): void;

    abstract protected function applyShow(): void;

    /** Destroy the native window. The executor is already released. Terminal. */
    abstract protected function destroyNative(): void;
}
