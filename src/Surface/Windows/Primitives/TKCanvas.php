<?php

namespace Surface\Windows\Primitives;

use Closure;
use Surface\Contracts\Framebuffers\DamageTrackingFramebuffer;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\FramebufferDriver;
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

    public function framebuffer(string $kind = 'full', ?int $width = null, ?int $height = null, int $frames = 2, ?string $driver = null): Framebuffer
    {
        $this->live();
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
        return $this->framebuffer;
    }

    public function present(): static
    {
        $this->live();
        $bound = $this->framebuffer ?? throw new WindowException("Canvas '{$this->path()}' has no framebuffer: call framebuffer() first.");

        if ($bound instanceof RingFramebuffer) {
            if ($this->shown === $bound->serial()) {
                return $this;
            }
            $this->shown = $bound->serial();
        } elseif ($bound instanceof DamageTrackingFramebuffer) {
            if (! is_null($this->shown) && $bound->damage() === []) {
                return $this;
            }
            $bound->beginEpoch();
            $this->shown = 0;
        } else {
            $this->shown = 0;
        }

        // Draining a ring reads its front frame; every kind here stores RGBA8, so the dump is the pixels.
        $this->applyPixels($bound->dump(), $bound->viewportWidth(), $bound->viewportHeight());

        return $this;
    }

    /** Device pixels per unit of size(): 2.0 on a HiDPI display. */
    abstract protected function nativeScale(): float;

    /**
     * Put RGBA8 pixels on screen: stretched over the view, the fourth byte ignored.
     *
     * @param  string  $rgba8  $width × $height × 4 bytes, top row first.
     */
    abstract protected function applyPixels(string $rgba8, int $width, int $height): void;
}
