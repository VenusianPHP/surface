<?php

namespace Surface\Contracts\Drawing;

use Closure;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Windows\Hdr;
use Surface\Contracts\Windows\ScaleFilter;
use Surface\Contracts\Windows\ScaleFit;

/**
 * A native surface a window has lent: its kind, its handles as addresses, and
 * its size in pixels read live from the window. The window releases it when
 * it reclaims the surface, before the native surface goes; a released surface
 * must not be presented into. A borrower registers with onRelease() what it
 * must do while the native surface still exists (a GL device frees its objects
 * in the lent context). It carries the window's vsync: the borrower presents
 * through its own swapchain, so it applies vsync() and follows onVsync().
 * It also carries the window's scaling (where a target of another size lands,
 * and how it is sampled) and reads the window's HDR state live.
 */
final class LentSurface
{
    private bool $released = false;

    /** @var list<Closure(): void> */
    private array $on_release = [];

    /** @var list<Closure(VSync): void> */
    private array $on_vsync = [];

    /** @var list<Closure(ScaleFilter, ScaleFit): void> */
    private array $on_scaling = [];

    /**
     * @param  array<string, int>  $handles  Native addresses by name; always the kind's handle().
     * @param  Closure(): array{int, int}  $size  The surface's size in device pixels, now.
     * @param  (Closure(): ?Hdr)|null  $hdr  The window's HDR state, now; null for a window that reports none.
     */
    public function __construct(
        public readonly SurfaceKind $kind,
        private readonly array $handles,
        private readonly Closure $size,
        private VSync $vsync = VSync::On,
        private ScaleFilter $filter = ScaleFilter::Linear,
        private ScaleFit $fit = ScaleFit::Stretch,
        private readonly ?Closure $hdr = null,
    ) {
        if (! isset($handles[$kind->handle()])) {
            throw new DrawingException("A {$kind->value} surface carries a '{$kind->handle()}' handle.");
        }
    }

    /** @throws DrawingException When the surface has no handle of that name. */
    public function handle(string $name): int
    {
        return $this->handles[$name]
            ?? throw new DrawingException("This {$this->kind->value} surface has no '{$name}' handle (it has: ".implode(', ', array_keys($this->handles)).').');
    }

    /** @return array<string, int> */
    public function handles(): array
    {
        return $this->handles;
    }

    /** @return array{int, int} width, height in device pixels */
    public function size(): array
    {
        return ($this->size)();
    }

    /** The vsync the window wants its frames shown with. */
    public function vsync(): VSync
    {
        return $this->vsync;
    }

    /**
     * Run $then with the new vsync each time the window's changes while lent.
     *
     * @param  Closure(VSync): void  $then
     */
    public function onVsync(Closure $then): void
    {
        $this->on_vsync[] = $then;
    }

    /** Called by the window when its vsync changes; nothing once released or when unchanged. */
    public function changeVsync(VSync $vsync): void
    {
        if ($this->released || $this->vsync === $vsync) {
            return;
        }
        $this->vsync = $vsync;
        foreach ($this->on_vsync as $closure) {
            $closure($vsync);
        }
    }

    /** @return array{ScaleFilter, ScaleFit} How the window wants a target of another size sampled, and where it lands. */
    public function scaling(): array
    {
        return [$this->filter, $this->fit];
    }

    /**
     * Run $then with the new filter and fit each time the window's scaling changes while lent.
     *
     * @param  Closure(ScaleFilter, ScaleFit): void  $then
     */
    public function onScaling(Closure $then): void
    {
        $this->on_scaling[] = $then;
    }

    /** Called by the window when its scaling changes; nothing once released or when unchanged. */
    public function changeScaling(ScaleFilter $filter, ScaleFit $fit): void
    {
        if ($this->released || [$this->filter, $this->fit] === [$filter, $fit]) {
            return;
        }
        $this->filter = $filter;
        $this->fit = $fit;
        foreach ($this->on_scaling as $closure) {
            $closure($filter, $fit);
        }
    }

    /** Where a target of $width × $height lands in the surface now, in its pixels; the device clears the rest. */
    public function presentRect(int $width, int $height): Region
    {
        [$across, $down] = $this->size();

        return $this->fit->rect($across, $down, $width, $height);
    }

    /** The window's HDR state now: sdrWhiteLevel is where an HDR target puts SDR white. Null where the window reports none. */
    public function hdr(): ?Hdr
    {
        return is_null($this->hdr) ? null : ($this->hdr)();
    }

    public function released(): bool
    {
        return $this->released;
    }

    /**
     * Called by the window when it reclaims the surface, while the native
     * surface still exists: marks it released, then runs what borrowers
     * registered, once, in the order registered. A borrower let go while it
     * still holds the surface calls it too, having first let go of everything
     * it made on the surface: the window then takes the surface back (its
     * onRelease() runs) before the borrower's own handles go.
     */
    public function release(): void
    {
        if ($this->released) {
            return;
        }
        $this->released = true;
        $then = $this->on_release;
        $this->on_release = [];
        foreach ($then as $closure) {
            $closure();
        }
    }

    /**
     * Run $then when the window releases the surface, before the native surface
     * goes; at once when it is already released.
     *
     * @param  Closure(): void  $then
     */
    public function onRelease(Closure $then): void
    {
        if ($this->released) {
            $then();

            return;
        }
        $this->on_release[] = $then;
    }
}
