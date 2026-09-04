<?php

namespace Surface\Contracts\NativeWindows\Views;

/**
 * A determinate progress bar over 0..1. For indeterminate busy, use the
 * spinner.
 */
interface OSProgressBar extends OSView
{
    /** The current progress, 0..1. */
    public function progress(): float;

    /** Write progress, clamped into 0..1. */
    public function setProgress(float $progress): static;
}
