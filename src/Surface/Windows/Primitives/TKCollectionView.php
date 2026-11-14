<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\CollectionItem;
use Surface\Contracts\Windows\Primitives\TKCollectionView as PrimitiveContract;
use Surface\Contracts\Windows\WindowException;

abstract class TKCollectionView extends TKItemView implements PrimitiveContract
{
    protected int $cell_width = 200;

    protected int $cell_height = 150;

    protected static function itemClass(): string
    {
        return CollectionItem::class;
    }

    public function cellSize(): array
    {
        return [$this->cell_width, $this->cell_height];
    }

    public function setCellSize(int $width, int $height): static
    {
        $this->live();
        if ($width < 1 || $height < 1) {
            throw new WindowException("Cell size must be at least 1x1, got {$width}x{$height}.");
        }
        [$this->cell_width, $this->cell_height] = [$width, $height];
        $this->applyCellSize($width, $height);

        return $this;
    }

    abstract protected function applyCellSize(int $width, int $height): void;
}
