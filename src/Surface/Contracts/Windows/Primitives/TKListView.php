<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Windows\WindowException;

/**
 * Items in one column of equal-height rows. Items are ListRow. A double click, Enter or Return
 * activates a row.
 */
interface TKListView extends TKItemView
{
    public function rowHeight(): int;

    /**
     * @return $this
     * @throws WindowException When the height is < 1.
     */
    public function setRowHeight(int $height): static;
}
