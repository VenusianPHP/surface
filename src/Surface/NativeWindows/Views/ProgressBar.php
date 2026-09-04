<?php

namespace Surface\NativeWindows\Views;

use Surface\Contracts\NativeWindows\Views\OSProgressBar;
use Surface\NativeWindows\Windowable;

/**
 * A determinate progress bar over 0..1. Output only — it emits nothing.
 * For indeterminate busy, use the spinner.
 */
abstract class ProgressBar extends View implements OSProgressBar
{
    public function __construct(
        string $name,
        Windowable $window,
        protected float $progress,
    ) {
        parent::__construct($name, $window);
        $this->progress = $this->clamp($progress);
    }

    public function progress(): float
    {
        return $this->progress;
    }

    public function setProgress(float $progress): static
    {
        $this->progress = $this->clamp($progress);
        $this->applyProgress($this->progress);

        return $this;
    }

    protected function clamp(float $progress): float
    {
        return max(0.0, min(1.0, $progress));
    }

    /**
     * Write the progress to the native bar.
     * @return void
     */
    abstract protected function applyProgress(float $progress): void;
}
