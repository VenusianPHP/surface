<?php

namespace Surface\Windows;

use Surface\Contracts\Windows\NativeVideo as NativeVideoContract;
use Surface\Contracts\Windows\VideoSource;
use Surface\Contracts\Windows\WindowException;

/**
 * VideoSource::Native, toolkit-neutral: where the view is placed, what of it
 * shows, and the checks. A stager's concrete builds the toolkit's player view
 * hidden, plays through the StagedVideo hooks, and moves the view in
 * applyFrame() and applyHidden(). The same placement twice reaches the native
 * once, so a caller may place() on every frame.
 */
abstract class NativeVideo extends StagedVideo implements NativeVideoContract
{
    /** @var array{float, float, float, float}|null */
    protected ?array $frame = null;

    /** @var array{float, float, float, float}|null the part of the frame showing; null while hidden */
    protected ?array $visible = null;

    /** @var array{float, float, float, float}|null the frame the native last took; null while hidden */
    private ?array $placed = null;

    public function source(): VideoSource
    {
        return VideoSource::Native;
    }

    public function place(float $x, float $y, float $width, float $height, ?array $clip = null): static
    {
        $this->live();
        $frame = [$x, $y, $width, $height];
        if (! is_null($clip) && count($clip) !== 4) {
            throw new WindowException("Video '{$this->name}' takes a clip of [x, y, width, height], got ".count($clip).' numbers.');
        }
        foreach ([...$frame, ...array_values($clip ?? [])] as $number) {
            if (! is_finite($number)) {
                throw new WindowException("Video '{$this->name}' is placed with finite numbers, got {$number}.");
            }
        }
        $this->frame = $frame;
        $visible = self::overlap($frame, is_null($clip) ? $frame : array_map(floatval(...), array_values($clip)));
        if (is_null($visible)) {
            return $this->conceal();
        }
        if ([$frame, $visible] !== [$this->placed, $this->visible]) {
            $this->placed = $frame;
            $this->visible = $visible;
            $this->applyFrame($frame, $visible);
        }

        return $this;
    }

    public function hide(): static
    {
        return $this->live()->conceal();
    }

    public function isShown(): bool
    {
        return ! is_null($this->visible);
    }

    public function frame(): ?array
    {
        return $this->frame;
    }

    private function conceal(): static
    {
        if (! is_null($this->visible)) {
            $this->visible = null;
            $this->placed = null;
            $this->applyHidden();
        }

        return $this;
    }

    /**
     * @param  array{float, float, float, float}  $a
     * @param  array{float, float, float, float}  $b
     * @return array{float, float, float, float}|null what both cover; null when that is nothing
     */
    private static function overlap(array $a, array $b): ?array
    {
        $left = max($a[0], $b[0]);
        $top = max($a[1], $b[1]);
        $right = min($a[0] + $a[2], $b[0] + $b[2]);
        $bottom = min($a[1] + $a[3], $b[1] + $b[3]);

        return $right > $left && $bottom > $top ? [$left, $top, $right - $left, $bottom - $top] : null;
    }

    /**
     * Show the player view with its box at $frame, only $visible of it showing. Both are
     * [x, y, width, height] in points from the window's top-left; $visible is inside $frame
     * and never empty.
     *
     * @param  array{float, float, float, float}  $frame
     * @param  array{float, float, float, float}  $visible
     */
    abstract protected function applyFrame(array $frame, array $visible): void;

    /** Take the player view off the window; playback goes on. */
    abstract protected function applyHidden(): void;
}
