<?php

namespace Surface\Contracts\Windows;

/**
 * A video the toolkit plays in a view of its own (AppKit's AVPlayerView, with
 * its controls), laid over the window's output: it is above everything the
 * window draws, whatever engine draws it. Nothing shows until place() says
 * where.
 */
interface NativeVideo extends StagedVideo
{
    /**
     * Show the view at ($x, $y), $width × $height, in points from the window's
     * top-left. Only the part of that box inside $clip shows; null shows all
     * of it. An empty box, or a clip that leaves nothing, hides the view.
     *
     * @param  array{float, float, float, float}|null  $clip  [x, y, width, height], in the same points
     *
     * @throws WindowException for a number that is not finite
     */
    public function place(float $x, float $y, float $width, float $height, ?array $clip = null): static;

    /** Take the view off the window until the next place(). Playback goes on. */
    public function hide(): static;

    /** Whether the view shows: placed, with something left inside its clip. */
    public function isShown(): bool;

    /** @return array{float, float, float, float}|null the box last placed, [x, y, width, height]; null before the first place() */
    public function frame(): ?array;
}
