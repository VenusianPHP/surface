<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Windows\Styling\ScrollPosition;
use Surface\Contracts\Windows\WindowException;

/**
 * A scrolling view of many items, each drawn from one fixed template. The toolkit builds
 * only the items in view, so a view holds thousands. Posts ItemActivated (an enabled item
 * opened: see each view for the gesture), ItemActionClicked (an enabled item's action), and
 * ViewScrolled while watched.
 */
interface TKItemView extends TKPrimitive
{
    public function count(): int;

    /**
     * @return list<object>
     */
    public function items(): array;

    /**
     * @throws WindowException When the index is outside the items.
     */
    public function item(int $index): object;

    /**
     * Replace every item.
     * @param list<object> $items
     * @return $this
     * @throws WindowException When an item is not of the view's item class.
     */
    public function setItems(array $items): static;

    /**
     * @param int $at 0..count(): the first new item's index
     * @param list<object> $items
     * @return $this
     * @throws WindowException When $at is out of range or an item is not of the view's item class.
     */
    public function insertItems(int $at, array $items): static;

    /**
     * The toolkit animates the removal where it does so natively.
     * @return $this
     * @throws WindowException When the range is outside the items or $count < 1.
     */
    public function removeItems(int $at, int $count = 1): static;

    /**
     * @return $this
     * @throws WindowException When the index is outside the items or the item is not of the view's item class.
     */
    public function updateItem(int $index, object $item): static;

    public function scrollPosition(): ScrollPosition;

    /**
     * The visible rect's top-left as content fractions (ScrollPosition's x and y), kept inside the content.
     * @return $this
     * @throws WindowException When a fraction is not finite.
     */
    public function scrollTo(float $x, float $y): static;

    /**
     * @return $this
     */
    public function watchScroll(bool $on = true): static;

    public function isWatchingScroll(): bool;
}
