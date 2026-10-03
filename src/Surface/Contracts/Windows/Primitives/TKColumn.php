<?php

namespace Surface\Contracts\Windows\Primitives;

/**
 * Children stacked in display order top to bottom; children reorder with moveBefore()/moveAfter()/moveTo().
 */
interface TKColumn extends TKPrimitiveGroup
{
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
