<?php

namespace Surface\NativeWindows\Components;

use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\Contracts\NativeWindows\Views\OSSpinner;
use Surface\NativeWindows\Windowable;

/**
 * A thin Component wrap over an indeterminate spinner. Conjured
 * stopped — the sketch decides when to spin.
 *
 * Parts: `spinner`.
 */
class ProgressSpinner extends Component
{
    public function __construct(
        Windowable $window,
        string $name,
        int $x,
        int $y,
        int $width,
        int $height,
        ?OSGroup $in = null,
    ) {
        parent::__construct($window, $name, $x, $y, $width, $height, $in);
    }

    protected function build(): void
    {
        $this->register('spinner', $this->root->spinner(
            $this->partName('spinner'),
            0,
            0,
            1,
            1,
        ));
    }

    protected function layout(): void
    {
        [$width, $height] = $this->innerSize();
        $this->parts['spinner']->place(0, 0, $width, $height);
    }

    /** The wrapped spinner, for anything not delegated below. */
    public function spinner(): OSSpinner
    {
        /** @var OSSpinner */
        return $this->parts['spinner'];
    }

    public function isSpinning(): bool
    {
        return $this->spinner()->isSpinning();
    }

    public function start(): static
    {
        $this->spinner()->start();

        return $this;
    }

    public function stop(): static
    {
        $this->spinner()->stop();

        return $this;
    }
}
