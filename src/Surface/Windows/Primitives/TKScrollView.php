<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKPrimitiveGroup as GroupContract;
use Surface\Contracts\Windows\Primitives\TKScrollView as PrimitiveContract;
use Surface\Contracts\Windows\WindowException;

/**
 * Scrolls exactly one column, row, grid or fixed. Leaves and a second container are
 * refused before the factory mints.
 */
abstract class TKScrollView extends TKPrimitiveGroup implements PrimitiveContract
{
    protected bool $scroll_horizontal = true;

    protected bool $scroll_vertical = true;

    public function setScrollbars(bool $horizontal, bool $vertical): static
    {
        $this->live();
        $this->scroll_horizontal = $horizontal;
        $this->scroll_vertical = $vertical;
        $this->applyScrollbars($horizontal, $vertical);

        return $this;
    }

    public function content(): ?GroupContract
    {
        return array_values($this->children)[0] ?? null;
    }

    protected function defaultPlacement(): Placement
    {
        return Placement::next();
    }

    protected function admitChild(string $name, bool $group, Placement $placement): void
    {
        if (! $group || $this->children !== []) {
            throw new WindowException("Scroll view '{$this->path()}' holds exactly one container (column, row, grid or fixed).");
        }
    }

    /**
     * @param bool $horizontal
     * @param bool $vertical
     * @return void
     */
    abstract protected function applyScrollbars(bool $horizontal, bool $vertical): void;
}
