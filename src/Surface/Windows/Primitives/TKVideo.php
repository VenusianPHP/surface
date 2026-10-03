<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKVideo as PrimitiveContract;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\WindowException;

/**
 * isPlaying() is the engine's last report (nativeStateChanged()), not the last call:
 * play() on a file that fails never plays. The driver posts VideoPlaying, VideoPaused,
 * VideoEnded and VideoFailed from the native signals.
 */
abstract class TKVideo extends TKPrimitive implements PrimitiveContract
{
    protected bool $playing = false;

    protected bool $muted = false;

    protected bool $looping = false;

    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        protected ?string $file,
    ) {
        parent::__construct($name, $window, $parent, $placement);
    }

    public function file(): ?string
    {
        return $this->file;
    }

    public function setFile(?string $file): static
    {
        $this->live();
        $this->file = $file;
        $this->applyFile($file);

        return $this;
    }

    public function play(): static
    {
        $this->live()->applyPlay();

        return $this;
    }

    public function pause(): static
    {
        $this->live()->applyPause();

        return $this;
    }

    public function seek(float $seconds): static
    {
        $this->live();
        if (! is_finite($seconds) || $seconds < 0.0) {
            throw new WindowException("Seek needs finite seconds >= 0, got {$seconds}.");
        }
        $this->applySeek($seconds);

        return $this;
    }

    public function position(): float
    {
        return $this->live()->nativePosition();
    }

    public function duration(): ?float
    {
        return $this->live()->nativeDuration();
    }

    public function isPlaying(): bool
    {
        return $this->playing;
    }

    public function setMuted(bool $muted): static
    {
        $this->live();
        $this->muted = $muted;
        $this->applyMuted($muted);

        return $this;
    }

    public function isMuted(): bool
    {
        return $this->muted;
    }

    public function setLoop(bool $loop): static
    {
        $this->live();
        $this->looping = $loop;
        $this->applyLoop($loop);

        return $this;
    }

    public function isLooping(): bool
    {
        return $this->looping;
    }

    /**
     * Engine callback: record whether the media is playing, post nothing.
     *
     * @param bool $playing
     * @return void
     */
    public function nativeStateChanged(bool $playing): void
    {
        $this->playing = $playing;
    }

    /**
     * @param string|null $file null unloads
     * @return void
     */
    abstract protected function applyFile(?string $file): void;

    abstract protected function applyPlay(): void;

    abstract protected function applyPause(): void;

    /**
     * @param float $seconds
     * @return void
     */
    abstract protected function applySeek(float $seconds): void;

    /**
     * @param bool $muted
     * @return void
     */
    abstract protected function applyMuted(bool $muted): void;

    /**
     * @param bool $loop
     * @return void
     */
    abstract protected function applyLoop(bool $loop): void;

    /**
     * @return float seconds from the start
     */
    abstract protected function nativePosition(): float;

    /**
     * @return float|null seconds; null until the media reports it
     */
    abstract protected function nativeDuration(): ?float;
}
