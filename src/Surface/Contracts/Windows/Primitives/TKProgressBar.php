<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Windows\WindowException;

/**
 * Progress as a 0..1 fraction, or indeterminate. Posts no mail.
 */
interface TKProgressBar extends TKPrimitive
{
    /**
     * @return float|null null = indeterminate
     */
    public function fraction(): ?float;

    /**
     * @param float|null $fraction 0..1, null = indeterminate
     * @return $this
     * @throws WindowException When outside 0..1.
     */
    public function setFraction(?float $fraction): static;
}
