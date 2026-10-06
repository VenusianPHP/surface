<?php

namespace Surface\Windows\Primitives;

use Closure;
use Surface\Contracts\Drawing\LentSurface;
use Surface\Contracts\Drawing\SurfaceBorrower;
use Surface\Contracts\Drawing\SurfaceKind;
use Surface\Contracts\Framebuffers\DamageTrackingFramebuffer;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Framebuffers\RingFramebuffer;
use Surface\Contracts\Windows\Primitives\TKCanvas as PrimitiveContract;
use Surface\Contracts\Windows\WindowException;
use Surface\Framebuffers\Extended\ExtendedFramebufferDriver;
use Surface\Framebuffers\Native\NativeFramebufferDriver;

/**
 * The canvas, toolkit-neutral: it makes and binds the framebuffer and decides
 * what present() has to show. A toolkit's canvas supplies the native view,
 * its scale, and the one call that puts RGBA8 bytes on screen.
 */
abstract class TKCanvas extends TKPrimitive implements PrimitiveContract
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
     * Where canvases get their framebuffer drivers: the application's FramebufferManager.
     * Without one, a canvas builds the named driver itself.
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
            throw new WindowException("Canvas '{$this->path()}' has lent its surface: its pixels are the borrower's. reclaim() it to draw into the canvas's own framebuffer.");
        }
        if (! in_array($kind, ['full', 'dirty', 'ring'], true)) {
            throw new WindowException("A canvas framebuffer is 'full', 'dirty' or 'ring', got '{$kind}'.");
        }
        if (is_null($width) || is_null($height)) {
            [$pixels_across, $pixels_down] = $this->pixelSize();
            $width ??= $pixels_across;
            $height ??= $pixels_down;
            if ($width < 1 || $height < 1) {
                throw new WindowException("Canvas '{$this->path()}' has no size yet: show its window first, or give framebuffer() a width and a height.");
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
        $bound = $this->framebuffer ?? throw new WindowException("Canvas '{$this->path()}' has no framebuffer: call framebuffer() first.");

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
            $this->applyPixels($bound->dump(), $width, $height);
        }

        return $this;
    }

    /** No toolkit lends until its engine slice says what it lends. */
    public function surfaces(): array
    {
        return [];
    }

    public function lend(SurfaceKind $kind, SurfaceBorrower $to): LentSurface
    {
        $this->live();
        if (! is_null($this->lent)) {
            throw new WindowException("Canvas '{$this->path()}' has already lent its {$this->lent->kind->value} surface: reclaim() it first.");
        }
        if (! in_array($kind, $this->surfaces(), true)) {
            $lends = implode(', ', array_map(fn (SurfaceKind $offered): string => $offered->value, $this->surfaces())) ?: 'none';

            throw new WindowException("Canvas '{$this->path()}' lends no {$kind->value} surface (it lends: {$lends}).");
        }

        $this->lent = new LentSurface($kind, $this->makeSurface($kind, $to->lendingHandles()), fn (): array => $this->pixelSize());
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

    /** Terminal, as every primitive's: a lent surface is reclaimed while the native view still exists. */
    public function remove(): void
    {
        $this->live();
        $this->reclaim();

        parent::remove();
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
     * Make the native surface of $kind inside the view. A toolkit's canvas
     * overrides this for every kind its surfaces() lists.
     *
     * @param  array<string, int>  $handles  The borrower's lendingHandles().
     * @return array<string, int> The surface's handles by name; always $kind->handle().
     */
    protected function makeSurface(SurfaceKind $kind, array $handles): array
    {
        throw new WindowException("Canvas '{$this->path()}' cannot make a {$kind->value} surface.");
    }

    /** Remove the native surface made by makeSurface(), after the lent surface was released; the view shows what applyPixels() / applyAddress() gives it again. */
    protected function removeSurface(SurfaceKind $kind): void {}

    /** Device pixels per unit of size(): 2.0 on a HiDPI display. */
    abstract protected function nativeScale(): float;

    /**
     * Put RGBA8 pixels on screen: stretched over the view, the fourth byte ignored.
     *
     * @param  string  $rgba8  $width × $height × 4 bytes, top row first.
     */
    abstract protected function applyPixels(string $rgba8, int $width, int $height): void;

    /**
     * Put the framebuffer's C memory on screen by its address: $stride bytes a
     * row, $height rows, top row first, stretched over the view, the fourth
     * byte ignored. $damage lists the regions changed since the last present;
     * [] is the whole frame. The toolkit copies what it needs before returning.
     *
     * @param  int  $address  The framebuffer's pointer(): trusted, $stride × $height readable bytes.
     * @param  list<Region>  $damage
     */
    abstract protected function applyAddress(int $address, int $width, int $height, int $stride, array $damage): void;
}
