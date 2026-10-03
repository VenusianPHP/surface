<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Windows\WindowException;

/**
 * One choice from a list of strings. Posts SelectionChanged.
 */
interface TKDropdown extends TKPrimitive
{
    /**
     * @return list<string>
     */
    public function options(): array;

    /**
     * The selection is kept when still in range, else the first option (-1 when empty).
     * @param list<string> $options
     * @return $this
     */
    public function setOptions(array $options): static;

    /**
     * @return int the selected index, -1 when there are no options
     */
    public function selected(): int;

    /**
     * Posts nothing: the app changed it, it knows.
     * @param int $index
     * @return $this
     * @throws WindowException When the index is out of range.
     */
    public function select(int $index): static;

    /**
     * @return string|null null when there are no options
     */
    public function selectedOption(): ?string;
}
