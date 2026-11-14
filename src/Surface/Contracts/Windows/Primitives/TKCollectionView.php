<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Windows\WindowException;

/**
 * Items in a grid of equal cells that wraps to the view's width. Items are CollectionItem. A
 * click on a cell activates it.
 */
interface TKCollectionView extends TKItemView
{
    /**
     * @return array{int, int} width and height in points
     */
    public function cellSize(): array;

    /**
     * @return $this
     * @throws WindowException When a side is < 1.
     */
    public function setCellSize(int $width, int $height): static;
}
