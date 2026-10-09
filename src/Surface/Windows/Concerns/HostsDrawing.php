<?php

namespace Surface\Windows\Concerns;

use Closure;
use Surface\Contracts\Drawing\LentSurface;
use Surface\Contracts\Drawing\SurfaceBorrower;
use Surface\Contracts\Drawing\SurfaceKind;
use Surface\Contracts\Drawing\VSync;
use Surface\Contracts\Framebuffers\DamageTrackingFramebuffer;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Framebuffers\RingFramebuffer;
use Surface\Contracts\Windows\Hdr;
use Surface\Contracts\Windows\ScaleFilter;
use Surface\Contracts\Windows\ScaleFit;
use Surface\Contracts\Windows\WindowException;
use Surface\Framebuffers\Extended\ExtendedFramebufferDriver;
use Surface\Framebuffers\Native\NativeFramebufferDriver;

/**
 * What a canvas and a staged window share: making and binding the framebuffer,
 * deciding what present() shows, and lending one native surface at a time.
 * The host supplies its native size and scale, the two calls that put pixels on
 * screen, the surfaces it can make, and the text that names it in messages.
 */
trait HostsDrawing
{
    /** @var (Closure(string|null): FramebufferDriver)|null */
    protected static ?Closure $framebuffers = null;

    protected ?Framebuffer $framebuffer = null;

    protected ?string $kind = null;

    /** The ring serial last shown, or whether a full or dirty framebuffer has been shown at all. */
    protected ?int $shown = null;

    protected ?LentSurface $lent = null;

    protected ?SurfaceBorrower $borrower = null;

    /**
     * Where hosts get their framebuffer drivers: the application's FramebufferManager.
     * Without one, a host builds the named driver itself. Static per using class.
     *
     * @param  (Closure(string|null): FramebufferDriver)|null  $resolver  Takes a driver name, or null for the default.
     */
    public static function resolveFramebuffersUsing(?Closure $resolver): void
    {
        static::$framebuffers = $resolver;
    }

    public function pixelSize(): array
    {
        $this->live();
        [$width, $height] = $this->nativeSize();
        $scale = $this->nativeScale();

        return [(int) round($width * $scale), (int) round($height * $scale)];
    }

    public function pixelFormat(): FormatSpec
    {
        return FormatSpec::rgba8();
    }

    public function framebuffer(string $kind = 'dirty', ?int $width = null, ?int $height = null, int $frames = 2, ?string $driver = null): Framebuffer
    {
        $this->live();
        if (! is_null($this->lent)) {
            throw new WindowException("{$this->hostName()} has lent its surface: its pixels are the borrower's. reclaim() it to draw into the {$this->hostKind()}'s own framebuffer.");
        }
        if (! in_array($kind, ['full', 'dirty', 'ring'], true)) {
            throw new WindowException("A {$this->hostKind()} framebuffer is 'full', 'dirty' or 'ring', got '{$kind}'.");
        }
        if (is_null($width) || is_null($height)) {
            [$pixels_across, $pixels_down] = $this->pixelSize();
            $width ??= $pixels_across;
            $height ??= $pixels_down;
            if ($width < 1 || $height < 1) {
                throw new WindowException("{$this->hostName()} has no size yet: show its window first, or give framebuffer() a width and a height.");
            }
        }

        $bound = $this->framebuffer;
        $same = ! is_null($bound) && $this->kind === $kind
            && $bound->viewportWidth() === $width && $bound->viewportHeight() === $height
            && (! $bound instanceof RingFramebuffer || $bound->frames() === $frames)
            && (is_null($driver) || ($bound->pointer() === 0) === ($driver === 'native'));
        if ($same) {
            return $bound;
        }

        $from = static::$framebuffers
            ? (static::$framebuffers)($driver)
            : (($driver ?? 'native') === 'native' ? new NativeFramebufferDriver() : new ExtendedFramebufferDriver());
        $this->framebuffer = match ($kind) {
            'full' => $from->full(FormatSpec::rgba8(), $width, $height),
            'dirty' => $from->dirty(FormatSpec::rgba8(), $width, $height),
            'ring' => $from->ring(FormatSpec::rgba8(), $width, $height, $frames),
        };
        $this->kind = $kind;
        $this->shown = null;

        return $this->framebuffer;
    }

    public function boundFramebuffer(): ?Framebuffer
    {
        return $this->borrower?->framebuffer() ?? $this->framebuffer;
    }

    public function canPipe(Framebuffer $framebuffer): bool
    {
        return $framebuffer->pointer() !== 0 && $framebuffer->hostFormat()->equals(FormatSpec::rgba8());
    }

    public function present(): static
    {
        $this->live();
        if (! is_null($this->lent) && ! is_null($this->borrower)) {
            return $this->presentLent($this->lent, $this->borrower);
        }
        $bound = $this->framebuffer ?? throw new WindowException("{$this->hostName()} has no framebuffer: call framebuffer() first.");
        $damage = [];
        if ($bound instanceof RingFramebuffer) {
            if ($this->shown === $bound->serial()) {
                return $this;
            }
            $damage = is_null($this->shown) ? [] : $bound->damage($this->shown);
            $this->shown = $bound->serial();
        } elseif ($bound instanceof DamageTrackingFramebuffer) {
            if (! is_null($this->shown) && $bound->damage() === []) {
                return $this;
            }
            $damage = is_null($this->shown) ? [] : $bound->damage();
            $bound->beginEpoch();
            $this->shown = 0;
        } else {
            $this->shown = 0;
        }
        $width = $bound->viewportWidth();
        $height = $bound->viewportHeight();
        // C memory in RGBA8: the toolkit copies it itself. PHP-held bytes go as a string.
        if ($this->canPipe($bound)) {
            $this->applyAddress($bound->pointer(), $width, $height, $width * 4, $damage);
        } else {
            $this->applyPixels($bound->dump(), $width, $height, $damage);
        }

        return $this;
    }

    public function lend(SurfaceKind $kind, SurfaceBorrower $to): LentSurface
    {
        $this->live();
        if (! is_null($this->lent)) {
            throw new WindowException("{$this->hostName()} has already lent its {$this->lent->kind->value} surface: reclaim() it first.");
        }
        if (! in_array($kind, $this->surfaces(), true)) {
            $lends = implode(', ', array_map(fn (SurfaceKind $offered): string => $offered->value, $this->surfaces())) ?: 'none';

            throw new WindowException("{$this->hostName()} lends no {$kind->value} surface (it lends: {$lends}).");
        }

        [$filter, $fit] = $this->lendingScaling();
        $this->lent = new LentSurface($kind, $this->makeSurface($kind, $to->lendingHandles()), fn (): array => $this->pixelSize(), $this->lendingVsync(), $filter, $fit, fn (): ?Hdr => $this->lendingHdr());
        $this->borrower = $to;
        $this->shown = null;

        return $this->lent;
    }

    public function lent(): ?LentSurface
    {
        return $this->lent;
    }

    public function reclaim(): void
    {
        if (is_null($this->lent)) {
            return;
        }

        // Released first: the borrower frees what it made while the native surface still exists.
        $lent = $this->lent;
        $lent->release();
        $this->removeSurface($lent->kind);
        $this->lent = null;
        $this->borrower = null;
        $this->shown = null;
    }

    /**
     * The borrower's GPU copy. Nothing when it has drawn nothing since the
     * last copy that landed; a skipped copy (no free drawable) leaves its
     * damage for the next present.
     */
    protected function presentLent(LentSurface $surface, SurfaceBorrower $borrower): static
    {
        $frame = $borrower->framebuffer();
        $tracked = $frame instanceof DamageTrackingFramebuffer;
        if (! is_null($this->shown) && $tracked && $frame->damage() === []) {
            return $this;
        }
        if ($borrower->presentInto($surface)) {
            $this->shown = 0;
            if ($tracked) {
                $frame->beginEpoch();
            }
        }

        return $this;
    }

    /**
     * Make the native surface of $kind. A host overrides this for every kind
     * its surfaces() lists.
     *
     * @param  array<string, int>  $handles  The borrower's lendingHandles().
     * @return array<string, int> The surface's handles by name; always $kind->handle().
     */
    protected function makeSurface(SurfaceKind $kind, array $handles): array
    {
        throw new WindowException("{$this->hostName()} cannot make a {$kind->value} surface.");
    }

    /** The vsync a lent surface starts with: the host's own, On for a host that has none. */
    protected function lendingVsync(): VSync
    {
        return VSync::On;
    }

    /** @return array{ScaleFilter, ScaleFit} The scaling a lent surface starts with: stretched over the host for a host that has none. */
    protected function lendingScaling(): array
    {
        return [ScaleFilter::Linear, ScaleFit::Stretch];
    }

    /** The HDR state a lent surface reads: none for a host that reports none. */
    protected function lendingHdr(): ?Hdr
    {
        return null;
    }

    /** Remove the native surface made by makeSurface(), after the lent surface was released; the host shows what applyPixels() / applyAddress() gives it again. */
    protected function removeSurface(SurfaceKind $kind): void {}

    /** The surfaces this host can make, in the order an engine should try them. */
    abstract public function surfaces(): array;

    /** Throws WindowException once the host is gone. */
    abstract protected function live(): static;

    /** The host in a message: "Canvas 'm.view'" or "Staged window 'game'". */
    abstract protected function hostName(): string;

    /** The host's noun in a message: "canvas" or "staged window". */
    abstract protected function hostKind(): string;

    /** Width and height in points. */
    abstract protected function nativeSize(): array;

    /** Device pixels per point: 2.0 on a HiDPI display. */
    abstract protected function nativeScale(): float;

    /**
     * Put RGBA8 pixels on screen: stretched over the host, the fourth byte ignored.
     * $damage lists the regions changed since the last present; [] is the whole frame.
     *
     * @param  string  $rgba8  $width × $height × 4 bytes, top row first.
     * @param  list<\Surface\Contracts\Framebuffers\Region>  $damage
     */
    abstract protected function applyPixels(string $rgba8, int $width, int $height, array $damage): void;

    /**
     * Put the framebuffer's C memory on screen by its address: $stride bytes a
     * row, $height rows, top row first, stretched over the host, the fourth
     * byte ignored. $damage lists the regions changed since the last present;
     * [] is the whole frame. The toolkit copies what it needs before returning.
     *
     * @param  int  $address  The framebuffer's pointer(): trusted, $stride × $height readable bytes.
     * @param  list<\Surface\Contracts\Framebuffers\Region>  $damage
     */
    abstract protected function applyAddress(int $address, int $width, int $height, int $stride, array $damage): void;
}
