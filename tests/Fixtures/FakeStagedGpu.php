<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\ColorSpace;
use Surface\Contracts\Drawing\LentSurface;
use Surface\Contracts\Drawing\PresentTiming;
use Surface\Contracts\Drawing\SurfaceKind;
use Surface\Contracts\Drawing\TargetFormat;
use Surface\Contracts\Drawing\VSync;
use Surface\Contracts\Framebuffers\GLFramebuffer as GLFramebufferContract;
use Surface\Contracts\Framebuffers\HdrReadback;
use Surface\Contracts\Framebuffers\Region;
use Surface\Drawing\Gpu\DrawList;
use Surface\Drawing\Gpu\GpuDevice;
use Surface\Drawing\Gpu\QueuesFrames;
use Surface\Drawing\Gpu\ReportsPresents;
use Surface\Drawing\Gpu\SwitchesVsync;
use Surface\Drawing\Gpu\TargetsFormats;
use Surface\Framebuffers\GLFramebuffer;

/**
 * A FakeGpuDevice that also takes the optional device interfaces staged
 * frames use. Every call lands in $inner->log, in order with the plain ones.
 */
final class FakeCapableGpuDevice implements GpuDevice, SwitchesVsync, TargetsFormats, QueuesFrames, ReportsPresents
{
    public FakeGpuDevice $inner;

    /** @var list<VSync> What vsyncModes() answers. */
    public array $modes = [VSync::On, VSync::Off];

    /** @var list<array{TargetFormat, ColorSpace}> What targetFormats() answers. */
    public array $formats = [[TargetFormat::Rgba16Float, ColorSpace::ExtendedLinearSrgb], [TargetFormat::Rgb10A2, ColorSpace::Hdr10Pq]];

    /** Presents that landed. */
    public int $submitted = 0;

    /** What lastPresented() answers. */
    public ?PresentTiming $shown = null;

    /** @var list<array{int, int}> Each waitPresented() call: frame, timeout. */
    public array $waits = [];

    /** @param  list<SurfaceKind>  $kinds */
    public function __construct(array $kinds = [SurfaceKind::METAL_LAYER])
    {
        $this->inner = new FakeGpuDevice($kinds, [], 'capable');
    }

    public function name(): string
    {
        return $this->inner->name();
    }

    public function target(int $width, int $height, int $samples): GLFramebufferContract
    {
        return $this->inner->target($width, $height, $samples);
    }

    public function draw(DrawList $list): void
    {
        $this->inner->draw($list);
    }

    public function surfaces(): array
    {
        return $this->inner->surfaces();
    }

    public function handles(): array
    {
        return $this->inner->handles();
    }

    public function adopt(LentSurface $surface): void
    {
        $this->inner->adopt($surface);
    }

    public function present(LentSurface $surface): bool
    {
        $landed = $this->inner->present($surface);
        if ($landed) {
            $this->submitted++;
        }

        return $landed;
    }

    public function release(): void
    {
        $this->inner->release();
    }

    public function vsyncModes(): array
    {
        return $this->modes;
    }

    public function applyVsync(VSync $vsync): void
    {
        $this->inner->log[] = "vsync {$vsync->value}";
    }

    public function targetFormats(): array
    {
        return $this->formats;
    }

    public function targetAs(int $width, int $height, int $samples, TargetFormat $format, ColorSpace $space): GLFramebufferContract
    {
        $this->inner->log[] = "target {$width}x{$height}x{$samples} {$format->value} {$space->value}";
        $this->inner->target = new FakeGLFramebuffer($width, $height);

        return new FakeFloatGLFramebuffer($this->inner->target);
    }

    public function setFramesInFlight(int $frames): void
    {
        $this->inner->log[] = "frames {$frames}";
    }

    public function submitted(): int
    {
        return $this->submitted;
    }

    public function lastPresented(): ?PresentTiming
    {
        return $this->shown;
    }

    public function waitPresented(int $frame, int $timeoutNs): bool
    {
        $this->waits[] = [$frame, $timeoutNs];

        return ! is_null($this->shown) && $this->shown->frame >= $frame;
    }
}

/** An HDR target over a FakeGLFramebuffer: RGBA8 as that one, and every pixel 2.0 red, 1.0 green, opaque in half floats. */
final class FakeFloatGLFramebuffer extends GLFramebuffer implements HdrReadback
{
    public function __construct(public readonly FakeGLFramebuffer $rgba)
    {
        parent::__construct();
    }

    public function width(): int
    {
        return $this->rgba->width();
    }

    public function height(): int
    {
        return $this->rgba->height();
    }

    public function readRgba8(Region $region): string
    {
        return $this->rgba->readRgba8($region);
    }

    public function uploadRgba8(string $rgba8, Region $region): void
    {
        $this->rgba->uploadRgba8($rgba8, $region);
    }

    public function readRgba16f(Region $region): string
    {
        return str_repeat(pack('v4', 0x4000, 0x3C00, 0x0000, 0x3C00), $region->width * $region->height);
    }
}
