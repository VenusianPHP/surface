<?php

namespace Surface\NativeWindows\Components;

use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\Contracts\NativeWindows\Views\OSProgressBar;
use Surface\NativeWindows\Windowable;

/**
 * A thin Component wrap over a determinate progress bar. Progress
 * (0..1, clamped on the view) delegates to the inner bar.
 *
 * Parts: `bar`.
 */
class ProgressBar extends Component
{
    public function __construct(
        Windowable $window,
        string $name,
        int $x,
        int $y,
        int $width,
        int $height,
        protected float $progress = 0.0,
        ?OSGroup $in = null,
    ) {
        parent::__construct($window, $name, $x, $y, $width, $height, $in);
    }

    protected function build(): void
    {
        $this->register('bar', $this->root->progressBar(
            $this->partName('bar'),
            $this->progress,
            0,
            0,
            1,
            1,
        ));
    }

    protected function layout(): void
    {
        [$width, $height] = $this->innerSize();
        $this->parts['bar']->place(0, 0, $width, $height);
    }

    /** The wrapped bar, for anything not delegated below. */
    public function bar(): OSProgressBar
    {
        /** @var OSProgressBar */
        return $this->parts['bar'];
    }

    public function progress(): float
    {
        return $this->bar()->progress();
    }

    public function setProgress(float $progress): static
    {
        $this->bar()->setProgress($progress);

        return $this;
    }
}
