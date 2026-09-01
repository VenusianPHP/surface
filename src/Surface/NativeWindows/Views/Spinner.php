<?php

namespace Surface\NativeWindows\Views;

use Surface\Contracts\NativeWindows\Views\OSSpinner;

/**
 * An indeterminate busy indicator. Surface holds the spinning flag it
 * believes in; every transition reaches the engine through applySpinning().
 *
 * Conjured stopped — showing motion nothing asked for would be a lie.
 */
abstract class Spinner extends View implements OSSpinner
{
    protected bool $spinning = false;

    public function isSpinning(): bool
    {
        return $this->spinning;
    }

    public function start(): static
    {
        $this->spinning = true;
        $this->applySpinning(true);

        return $this;
    }

    public function stop(): static
    {
        $this->spinning = false;
        $this->applySpinning(false);

        return $this;
    }

    /**
     * Push a spinning state to the native node — start or stop animating.
     * @return void
     */
    abstract protected function applySpinning(bool $spinning): void;
}
