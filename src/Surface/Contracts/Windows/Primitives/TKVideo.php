<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Windows\WindowException;

/**
 * A media file played in a view. Posts VideoPlaying, VideoPaused, VideoEnded and VideoFailed
 * as the engine reports them; isPlaying() is the engine's last report.
 */
interface TKVideo extends TKPrimitive
{
    /**
     * @return string|null
     */
    public function file(): ?string;

    /**
     * @param string|null $file null unloads
     * @return $this
     */
    public function setFile(?string $file): static;

    /**
     * @return $this
     */
    public function play(): static;

    /**
     * @return $this
     */
    public function pause(): static;

    /**
     * @param float $seconds
     * @return $this
     * @throws WindowException When negative.
     */
    public function seek(float $seconds): static;

    /**
     * Seconds from the start, read from the engine.
     * @return float
     */
    public function position(): float;

    /**
     * Seconds, read from the engine; null until the media reports it.
     * @return float|null
     */
    public function duration(): ?float;

    /**
     * @return bool
     */
    public function isPlaying(): bool;

    /**
     * @param bool $muted
     * @return $this
     */
    public function setMuted(bool $muted): static;

    /**
     * @return bool
     */
    public function isMuted(): bool;

    /**
     * @param bool $loop
     * @return $this
     */
    public function setLoop(bool $loop): static;

    /**
     * @return bool
     */
    public function isLooping(): bool;
}
