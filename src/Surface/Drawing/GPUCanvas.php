<?php

namespace Surface\Drawing;

use Surface\Contracts\Drawing\CPUDrawTarget;
use Surface\Contracts\Drawing\CPUEngine;
use Surface\Contracts\Drawing\Drawing2D;
use Surface\Contracts\Drawing\Executor;
use Surface\Contracts\Drawing\GPUEngine;
use Surface\Contracts\Drawing\GPUHost;
use Surface\Contracts\Drawing\GPUEngineDriver;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Region;
use Surface\Drawing\Concerns\RunsFrames;
use Surface\Framebuffers\Packings\Packing;
use Surface\Framebuffers\PixelMapper;

/**
 * A GPU engine rendering for something that wants bytes, not a window.
 *
 * The engine attaches to no host at all — no native view, no lent layer — and
 * draws into a surface it made itself. After the frame, readPixels() brings
 * the RGBA8 back and the packing turns it into the consumer's own format, so
 * an embedded display receives exactly the bytes its transmit() wants.
 *
 * It answers CPUDrawTarget because that is what a consumer of finished pixels
 * asks for; engine() names the CPU engine it stands in for, and gpuEngine()
 * the one actually rasterising. Damage is the whole surface every frame: a
 * GPU clears and redraws, so nothing smaller is true.
 */
class GPUCanvas implements CPUDrawTarget
{
    use RunsFrames;

    protected Packing $packing;

    protected PixelMapper $mapper;

    protected string $rgba8 = '';

    public function __construct(
        protected GPUEngine $gpu_engine,
        protected Executor $executor,
        protected int $width,
        protected int $height,
        protected FormatSpec $format,
    ) {
        $this->bootFrames($executor);
        $this->packing = Packing::for($format, $width, $height);
        $this->mapper = PixelMapper::for($format);
        $this->rgba8 = str_repeat("\x00\x00\x00\xff", $width * $height);
    }

    /** Attach a GPU engine to nothing and draw at this size in this format. */
    public static function headless(GPUEngineDriver $engine, int $width, int $height, FormatSpec $format): static
    {
        $attachment = $engine->attach(new GPUHost(native_view: 0, width: $width, height: $height, scale: 1.0));

        return new static($engine->engine(), $attachment->executor, $width, $height, $format);
    }

    public function gpuEngine(): GPUEngine
    {
        return $this->gpu_engine;
    }

    /** What a GPU redraw is, in CPU-engine terms: the whole surface, every frame. */
    public function engine(): CPUEngine
    {
        return CPUEngine::FULL;
    }

    public function hostFormat(): FormatSpec
    {
        return $this->format;
    }

    public function drawableSize(): array
    {
        return [$this->width, $this->height];
    }

    public function damage(): array
    {
        return [Region::wholeSurface($this->width, $this->height)];
    }

    public function rgba8(): string
    {
        return $this->rgba8;
    }

    public function flush(?FormatSpec $spec = null, bool $as_array = false): string|array
    {
        $bytes = is_null($spec) || $spec->equals($this->format)
            ? $this->packing->fromRgba8($this->rgba8, $this->mapper)
            : Packing::for($spec, $this->width, $this->height)->fromRgba8($this->rgba8, PixelMapper::for($spec));

        return $as_array ? array_values(unpack('C*', $bytes)) : $bytes;
    }

    public function flushRegion(Region $region, ?FormatSpec $spec = null, bool $as_array = false): string|array
    {
        $crop = $region->intersect(Region::wholeSurface($this->width, $this->height));

        if (is_null($crop)) {
            return $as_array ? [] : '';
        }

        $spec ??= $this->format;
        $packing = Packing::for($spec, $crop->width, $crop->height);
        $mapper = PixelMapper::for($spec);
        $rows = '';

        for ($y = 0; $y < $crop->height; $y++) {
            $rows .= substr($this->rgba8, (($crop->y + $y) * $this->width + $crop->x) * 4, $crop->width * 4);
        }

        $bytes = $packing->fromRgba8($rows, $mapper);

        return $as_array ? array_values(unpack('C*', $bytes)) : $bytes;
    }

    public function drawing(): Drawing2D
    {
        return $this->painter;
    }

    /** The frame the engine draws, then the pixels it drew, kept for whoever asks. */
    public function renderFrame(): bool
    {
        if (! $this->frameVisible() || ! $this->frameWanted()) {
            return false;
        }

        if (! $this->executor->beginFrame($this->clear_color)) {
            return false;
        }

        $frame = $this->nextFrame($this->width, $this->height, 1.0);

        try {
            $this->painter->begin($this->width, $this->height, 1.0);
            ($this->on_draw)($this->painter, $frame);
            $this->painter->flush();
            $this->rgba8 = $this->executor->readPixels();
        } finally {
            $this->painter->reset();
            $this->executor->endFrame();
        }

        return true;
    }

    public function executor(): Executor
    {
        return $this->executor;
    }

    public function release(): void
    {
        $this->executor->release();
    }

    protected function frameSize(): array
    {
        return [$this->width, $this->height];
    }

    protected function frameScale(): float
    {
        return 1.0;
    }

    protected function frameVisible(): bool
    {
        return true;
    }
}
