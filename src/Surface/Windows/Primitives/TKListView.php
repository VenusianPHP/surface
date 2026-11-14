<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\ListRow;
use Surface\Contracts\Windows\Primitives\TKListView as PrimitiveContract;
use Surface\Contracts\Windows\WindowException;

abstract class TKListView extends TKItemView implements PrimitiveContract
{
    protected int $row_height = 64;

    protected static function itemClass(): string
    {
        return ListRow::class;
    }

    public function rowHeight(): int
    {
        return $this->row_height;
    }

    public function setRowHeight(int $height): static
    {
        $this->live();
        if ($height < 1) {
            throw new WindowException("Row height must be at least 1, got {$height}.");
        }
        $this->row_height = $height;
        $this->applyRowHeight($height);

        return $this;
    }

    abstract protected function applyRowHeight(int $height): void;
}
