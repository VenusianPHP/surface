<?php

namespace Surface\NativeWindows\Views;

use Surface\Contracts\NativeWindows\Views\OSVideo;
use Surface\NativeWindows\Windowable;

/**
 * Moving pictures from a file path. Surface holds the path and the
 * playing/muted flags it believes in; the engine loads and plays through
 * the three hooks. Both engines ship native transport controls, so a
 * sketch may never call play() at all and let the viewer press the button.
 *
 * Conjured paused — sound nothing asked for would be worse than motion
 * nothing asked for.
 */
abstract class Video extends View implements OSVideo
{
    protected bool $playing = false;

    protected bool $muted = false;

    public function __construct(
        string $name,
        Windowable $window,
        protected ?string $path = null,
    ) {
        parent::__construct($name, $window);
    }

    public function path(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;
        $this->applyPath($path);

        return $this;
    }

    public function play(): static
    {
        $this->playing = true;
        $this->applyPlaying(true);

        return $this;
    }

    public function pause(): static
    {
        $this->playing = false;
        $this->applyPlaying(false);

        return $this;
    }

    public function isPlaying(): bool
    {
        return $this->playing;
    }

    public function setMuted(bool $muted): static
    {
        $this->muted = $muted;
        $this->applyMuted($muted);

        return $this;
    }

    public function isMuted(): bool
    {
        return $this->muted;
    }

    /**
     * Load the file into the native player. Called for every setPath();
     * the mint hook owns any initial load when a path was conjured in.
     * @return void
     */
    abstract protected function applyPath(string $path): void;

    /**
     * Start or stop playback. Safe with no media loaded.
     * @return void
     */
    abstract protected function applyPlaying(bool $playing): void;

    /**
     * Mute or unmute. Safe with no media loaded.
     * @return void
     */
    abstract protected function applyMuted(bool $muted): void;
}
