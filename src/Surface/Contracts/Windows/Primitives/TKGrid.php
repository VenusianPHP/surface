<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Windows\WindowException;

/**
 * Children in cells. at() sets the cell the next creation call uses; creating
 * without one, or into a taken cell, throws WindowException.
 */
interface TKGrid extends TKPrimitiveGroup
{
    /**
     * @param int $row
     * @param int $column
     * @param int $rowSpan
     * @param int $columnSpan
     * @return $this
     * @throws WindowException When row/column are negative or a span is below 1.
     */
    public function at(int $row, int $column, int $rowSpan = 1, int $columnSpan = 1): static;

    /**
     * @return int
     */
    public function spacing(): int;

    /**
     * @param int $spacing
     * @return $this
     */
    public function setSpacing(int $spacing): static;
}
