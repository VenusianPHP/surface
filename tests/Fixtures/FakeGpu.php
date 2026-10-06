<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\LentSurface;
use Surface\Contracts\Drawing\SurfaceBorrower;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Drawing\SurfaceKind;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\GLFramebuffer as GLFramebufferContract;
use Surface\Contracts\Framebuffers\Region;
use Surface\Drawing\Gpu\DrawList;
use Surface\Drawing\Gpu\GpuDevice;
use Surface\Drawing\Gpu\Op;
use Surface\Framebuffers\GLFramebuffer;
use Surface\Framebuffers\Native\NativeFullFramebuffer;

/**
 * A GLFramebuffer whose GPU is a native RGBA8 framebuffer: $pixels is the
 * target, written directly by whatever plays the engine. Every readback and
 * upload is kept, so tests can count the round trips.
 */
final class FakeGLFramebuffer extends GLFramebuffer
{
    public NativeFullFramebuffer $pixels;

    /** @var list<Region> */
    public array $reads = [];

    /** @var list<Region> */
    public array $uploads = [];

    public function __construct(private readonly int $w, private readonly int $h)
    {
        parent::__construct();
        $this->pixels = new NativeFullFramebuffer(FormatSpec::rgba8(), $w, $h);
    }

    public function width(): int
    {
        return $this->w;
    }

    public function height(): int
    {
        return $this->h;
    }

    public function readRgba8(Region $region): string
    {
        $this->reads[] = $region;

        return $this->pixels->flushRegion($region, FormatSpec::rgba8());
    }

    public function uploadRgba8(string $rgba8, Region $region): void
    {
        $this->uploads[] = $region;
        $this->pixels->writeRgba8($rgba8, $region->width, $region->height, $region->x, $region->y);
    }
}

/** Who a fake canvas lends to: it keeps every surface it was asked to present into. */
final class FakeBorrower implements SurfaceBorrower
{
    /** @var list<LentSurface> */
    public array $presented = [];

    /** Whether a drawable is free: presentInto() answers it. */
    public bool $free = true;

    /** @param array<string, int> $handles */
    public function __construct(public FakeGLFramebuffer $target, public array $handles = []) {}

    public function framebuffer(): Framebuffer
    {
        return $this->target;
    }

    public function lendingHandles(): array
    {
        return $this->handles;
    }

    public function presentInto(LentSurface $surface): bool
    {
        $this->presented[] = $surface;

        return $this->free;
    }
}

/**
 * A GPU device with no GPU. It keeps every call in $log and every list in
 * $lists, and carries out the operations that need no rasteriser (CLEAR,
 * SOLID, RECTS, under the scissor) on its target's pixels, so frames of
 * clears and whole-pixel text have real bytes.
 */
final class FakeGpuDevice implements GpuDevice
{
    /** @var list<DrawList> */
    public array $lists = [];

    /** @var list<string> Calls in order: 'adopt', 'target 80x60x4', 'draw', 'present', 'release'. */
    public array $log = [];

    public ?FakeGLFramebuffer $target = null;

    /** Whether a drawable is free: present() answers it. */
    public bool $free = true;

    /**
     * @param  list<SurfaceKind>  $kinds  What it presents into, best first.
     * @param  array<string, int>  $handles
     */
    public function __construct(public array $kinds = [], public array $handles = [], public string $name = 'fake') {}

    public function name(): string
    {
        return $this->name;
    }

    public function target(int $width, int $height, int $samples): GLFramebufferContract
    {
        $this->log[] = "target {$width}x{$height}x{$samples}";

        return $this->target = new FakeGLFramebuffer($width, $height);
    }

    public function draw(DrawList $list): void
    {
        $this->log[] = 'draw';
        $this->lists[] = $list;
        $scissor = Region::wholeSurface($list->width, $list->height);
        foreach ($list->operations as $operation) {
            match ($operation[0]) {
                Op::CLEAR => $this->target->pixels->fill($operation[1]),
                Op::SCISSOR => $scissor = $operation[1],
                Op::SOLID => $this->quads($list, $operation[1], 6, $operation[2], $scissor),
                Op::RECTS => $this->quads($list, $operation[1], $operation[2], $operation[3], $scissor),
                default => null,
            };
        }
    }

    public function surfaces(): array
    {
        return $this->kinds;
    }

    public function handles(): array
    {
        return $this->handles;
    }

    public function adopt(LentSurface $surface): void
    {
        $this->log[] = 'adopt';
    }

    public function present(LentSurface $surface): bool
    {
        $this->log[] = 'present';

        return $this->free;
    }

    public function release(): void
    {
        $this->log[] = 'release';
    }

    /** Fill each quad of whole pixels, cut by the scissor. */
    private function quads(DrawList $list, int $first, int $count, int $rgba, Region $scissor): void
    {
        $xy = array_values(unpack('g*', substr($list->vertices, $first * 8, $count * 8)));
        for ($vertex = 0; $vertex < $count; $vertex += 6) {
            $x = (int) $xy[$vertex * 2];
            $y = (int) $xy[$vertex * 2 + 1];
            $area = (new Region($x, $y, (int) $xy[$vertex * 2 + 4] - $x, (int) $xy[$vertex * 2 + 5] - $y))->intersect($scissor);
            if (! is_null($area)) {
                $this->target->pixels->setSegment($area->x, $area->y, $area->width, $area->height, $rgba);
            }
        }
    }
}

/** A device whose target cannot be made. */
abstract class FakeGpuDeviceThatFails implements GpuDevice
{
    /** @param list<SurfaceKind> $kinds */
    public function __construct(public array $kinds = []) {}

    public function name(): string { return 'failing'; }

    public function target(int $width, int $height, int $samples): GLFramebufferContract { throw new RuntimeException('no memory'); }

    public function draw(DrawList $list): void {}

    public function surfaces(): array { return $this->kinds; }

    public function handles(): array { return []; }

    public function adopt(LentSurface $surface): void {}

    public function present(LentSurface $surface): bool { return false; }

    public function release(): void {}
}
