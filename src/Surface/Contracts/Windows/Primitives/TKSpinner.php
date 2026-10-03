<?php

namespace Surface\Contracts\Windows\Primitives;

/**
 * An indeterminate activity indicator. Posts no mail.
 */
interface TKSpinner extends TKPrimitive
{
    /**
     * @return $this
     */
    public function start(): static;

    /**
     * @return $this
     */
    public function stop(): static;

    /**
     * @return bool
     */
    public function isSpinning(): bool;
}
