<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Windows\WindowException;

/**
 * A standalone scroll bar over a range the app owns: the value runs 0..max(0, maximum - page),
 * the thumb is page / maximum of the track. The user's moves (thumb drag, a track click as the
 * platform moves, a wheel over the bar: step per wheel line) post ScrollChanged; the app's post
 * nothing.
 */
interface TKScrollBar extends TKPrimitive
{
    /**
     * @return bool
     */
    public function vertical(): bool;

    /**
     * Clamps the value into the new range. Posts nothing.
     * @param int $maximum
     * @param int $page
     * @return $this
     * @throws WindowException When maximum or page is negative.
     */
    public function setRange(int $maximum, int $page): static;

    /**
     * @return int
     */
    public function maximum(): int;

    /**
     * @return int
     */
    public function page(): int;

    /**
     * The amount one wheel line moves. Default 1.
     * @param int $line
     * @return $this
     * @throws WindowException When line is under 1.
     */
    public function setStep(int $line): static;

    /**
     * @return int
     */
    public function step(): int;

    /**
     * Clamped into the range. Posts nothing.
     * @param int $value
     * @return $this
     */
    public function setValue(int $value): static;

    /**
     * @return int
     */
    public function value(): int;
}
