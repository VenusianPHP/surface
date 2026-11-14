<?php

namespace Surface\Windows;

use Surface\Contracts\Windows\Primitives\PrimitiveRegistry;
use Surface\Contracts\Windows\StagedVideo as StagedVideoContract;
use Surface\Contracts\Windows\WindowException;
use Voyager\NutsAndBolts\Str;

/**
 * State, identity and the removed guard every staged video shares, as
 * Primitives\TKVideo holds a toolkit window's: the same state, the same apply*
 * and native* hooks, so one player can serve both. isPlaying() is the source's
 * last report (nativeStateChanged()), not the last call. The concrete posts
 * VideoPlaying, VideoPaused, VideoEnded and VideoFailed, with path() as the
 * path. Abstracts never call a hook from their constructor: a concrete calls
 * parent::__construct() first, then loads its file.
 */
abstract class StagedVideo implements StagedVideoContract
{
    protected readonly string $uuid;

    protected bool $removed = false;

    protected bool $playing = false;

    protected bool $muted = false;

    protected bool $looping = false;

    /**
     * @throws WindowException When the name is not valid.
     */
    public function __construct(
        protected readonly string $name,
        protected readonly StagedWindow $window,
        protected ?string $file,
    ) {
        PrimitiveRegistry::guardName($name);
        $this->uuid = Str::uuid()->toString();
    }

    public function name(): string
    {
        return $this->name;
    }

    /** The path its mail carries: a staged window's videos have no parents, so the name. */
    public function path(): string
    {
        return $this->name;
    }

    public function uuid(): string
    {
        return $this->uuid;
    }

    public function window(): StagedWindow
    {
        return $this->window;
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

    public function isRemoved(): bool
    {
        return $this->removed;
    }

    /**
     * @throws WindowException When already removed.
     */
    public function remove(): void
    {
        $this->live();
        $this->removed = true;
        $this->playing = false;
        $this->releaseNative();
        $this->window->forgetVideo($this);
    }

    /** Source callback: record whether the media is playing, post nothing. */
    public function nativeStateChanged(bool $playing): void
    {
        $this->playing = $playing;
    }

    protected function post(object $mail): void
    {
        $this->window->session()->post($mail);
    }

    /**
     * @throws WindowException Once removed.
     */
    protected function live(): static
    {
        if ($this->removed) {
            throw new WindowException("Video '{$this->name}' was removed.");
        }

        return $this;
    }

    /** @param  string|null  $file  null unloads */
    abstract protected function applyFile(?string $file): void;

    abstract protected function applyPlay(): void;

    abstract protected function applyPause(): void;

    abstract protected function applySeek(float $seconds): void;

    abstract protected function applyMuted(bool $muted): void;

    abstract protected function applyLoop(bool $loop): void;

    /** @return float seconds from the start */
    abstract protected function nativePosition(): float;

    /** @return float|null seconds; null until the media reports it */
    abstract protected function nativeDuration(): ?float;

    /** Stop playback and let go of everything the source holds; called once, by remove(). */
    abstract protected function releaseNative(): void;
}
