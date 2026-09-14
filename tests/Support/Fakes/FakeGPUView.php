<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\NativeWindows\Views\GPUView;

/** A GPUView twin with no native: records frames, resizes its executor in pixels. */
final class FakeGPUView extends GPUView
{
    /** @var list<array{int, int, int, int}> */
    public array $applied_frames = [];

    /** @var list<bool> */
    public array $applied_visible = [];

    public bool $destroyed = false;

    public int $native_queues = 0;

    public bool $self_driving = false;

    public function __construct(
        string $name,
        \Surface\NativeWindows\Windowable $window,
        \Surface\Contracts\Drawing\GPUEngine $gpu_engine,
        \Surface\Contracts\Drawing\Executor $executor,
        float $scale = 1.0,
        public readonly ?\Surface\Contracts\Drawing\GLSurface $gl = null,
    ) {
        parent::__construct($name, $window, $gpu_engine, $executor, $scale);
    }

    protected function applyFrame(int $x, int $y, int $width, int $height): void
    {
        $this->applied_frames[] = [$x, $y, $width, $height];
        $this->executor->resize((int) round($width * $this->scale), (int) round($height * $this->scale));
    }

    protected function destroyNative(): void
    {
        $this->destroyed = true;
    }

    protected function applyVisible(bool $visible): void
    {
        $this->applied_visible[] = $visible;
    }

    public function drivesOwnFrames(): bool
    {
        return $this->self_driving;
    }

    protected function queueNativeFrame(): void
    {
        $this->native_queues++;
    }

    /** Test door: what a twin does when the window's backing scale changes. */
    public function rescale(float $scale): void
    {
        $this->setScale($scale);
        $this->relayout();
    }
}
