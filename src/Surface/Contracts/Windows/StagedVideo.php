<?php

namespace Surface\Contracts\Windows;

/**
 * A media file played in a staged window, as TKVideo is one played in a
 * toolkit window: a NativeVideo or a DecodedVideo, by the VideoSource it was
 * asked for. Posts VideoPlaying, VideoPaused, VideoEnded and VideoFailed (view
 * mail, the video's name as its path) as its source reports them; isPlaying()
 * is the source's last report.
 */
interface StagedVideo
{
    /** Unique among the window's videos; the path its mail carries. */
    public function name(): string;

    public function uuid(): string;

    public function window(): StagedWindow;

    public function source(): VideoSource;

    public function file(): ?string;

    /**
     * Playback stops; the new file starts paused at its start.
     *
     * @param  string|null  $file  null unloads
     */
    public function setFile(?string $file): static;

    /** At the end, starts again from the start. */
    public function play(): static;

    public function pause(): static;

    /** @throws WindowException when $seconds is negative or not finite */
    public function seek(float $seconds): static;

    /** Seconds from the start, read from the source. */
    public function position(): float;

    /** Seconds, read from the source; null until the media reports it. */
    public function duration(): ?float;

    public function isPlaying(): bool;

    public function setMuted(bool $muted): static;

    public function isMuted(): bool;

    public function setLoop(bool $loop): static;

    public function isLooping(): bool;

    public function isRemoved(): bool;

    /**
     * Terminal: playback stops, what the source holds is released and the
     * window forgets the name; every later call throws. Closing the window
     * removes its videos.
     */
    public function remove(): void;
}
