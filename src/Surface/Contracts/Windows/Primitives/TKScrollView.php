<?php

namespace Surface\Contracts\Windows\Primitives;

/**
 * Scrolls exactly one container (column, row, grid or fixed). A leaf, or a second
 * container, throws WindowException.
 */
interface TKScrollView extends TKPrimitiveGroup
{
    /**
     * @param bool $horizontal
     * @param bool $vertical
     * @return $this
     */
    public function setScrollbars(bool $horizontal, bool $vertical): static;

    /**
     * The scrolled container, once created.
     * @return TKPrimitiveGroup|null
     */
    public function content(): ?TKPrimitiveGroup;
}
