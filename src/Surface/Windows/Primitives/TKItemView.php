<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKItemView as PrimitiveContract;
use Surface\Contracts\Windows\Styling\ScrollPosition;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\WindowException;

/**
 * Surface keeps every item; the native asks for the ones in view. The driver posts
 * ItemActivated and ItemActionClicked from the native signals for enabled() items only, and
 * ViewScrolled (latest-only) from the native's scroll signal while isWatchingScroll().
 */
abstract class TKItemView extends TKPrimitive implements PrimitiveContract
{
    /**
     * @var list<object>
     */
    protected array $items;

    protected bool $watching_scroll = false;

    /**
     * @param list<object> $items
     * @throws WindowException When the name is not valid or an item is not of the view's item class.
     */
    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        array $items,
    ) {
        $this->items = $this->guardItems($items);
        parent::__construct($name, $window, $parent, $placement);
    }

    /**
     * @return class-string
     */
    abstract protected static function itemClass(): string;

    public function count(): int
    {
        return count($this->items);
    }

    public function items(): array
    {
        return $this->items;
    }

    public function item(int $index): object
    {
        return $this->items[$this->guardIndex($index)];
    }

    public function setItems(array $items): static
    {
        $this->live();
        $this->items = $this->guardItems($items);
        $this->applyItems($this->items);

        return $this;
    }

    public function insertItems(int $at, array $items): static
    {
        $this->live();
        if ($at < 0 || $at > count($this->items)) {
            throw new WindowException("Insert index {$at} is out of range (0..".count($this->items).').');
        }
        $items = $this->guardItems($items);
        if ($items === []) {
            return $this;
        }
        array_splice($this->items, $at, 0, $items);
        $this->applyInserted($at, $items);

        return $this;
    }

    public function removeItems(int $at, int $count = 1): static
    {
        $this->live();
        if ($count < 1 || $at < 0 || $at + $count > count($this->items)) {
            throw new WindowException("Items {$at}..".($at + $count - 1).' are out of range ('.count($this->items).' items).');
        }
        array_splice($this->items, $at, $count);
        $this->applyRemoved($at, $count);

        return $this;
    }

    public function updateItem(int $index, object $item): static
    {
        $this->live();
        $this->guardIndex($index);
        $this->items[$index] = $this->guardItems([$item])[0];
        $this->applyUpdated($index, $item);

        return $this;
    }

    public function scrollPosition(): ScrollPosition
    {
        return $this->live()->nativeScrollPosition();
    }

    public function scrollTo(float $x, float $y): static
    {
        $this->live();
        if (! is_finite($x) || ! is_finite($y)) {
            throw new WindowException("Item view '{$this->path()}' scroll position must be finite.");
        }
        $this->applyScrollTo(max(0.0, min(1.0, $x)), max(0.0, min(1.0, $y)));

        return $this;
    }

    public function watchScroll(bool $on = true): static
    {
        $this->live();
        $this->watching_scroll = $on;

        return $this;
    }

    public function isWatchingScroll(): bool
    {
        return $this->watching_scroll;
    }

    /**
     * Whether the native may post for the item: it exists and is not disabled.
     */
    public function enabled(int $index): bool
    {
        $item = $this->items[$index] ?? null;

        return $item !== null && (! property_exists($item, 'enabled') || $item->enabled);
    }

    /**
     * @param array<mixed> $items
     * @return list<object>
     * @throws WindowException
     */
    protected function guardItems(array $items): array
    {
        $class = static::itemClass();
        if (! array_is_list($items) || array_filter($items, fn (mixed $item): bool => ! $item instanceof $class) !== []) {
            throw new WindowException('Items must be a list of '.$class.'.');
        }

        return $items;
    }

    /**
     * @throws WindowException When outside the items.
     */
    protected function guardIndex(int $index): int
    {
        if ($index < 0 || $index >= count($this->items)) {
            throw new WindowException("Item {$index} is out of range (".count($this->items).' items).');
        }

        return $index;
    }

    /**
     * Replace every native item.
     * @param list<object> $items
     */
    abstract protected function applyItems(array $items): void;

    /**
     * @param list<object> $items
     */
    abstract protected function applyInserted(int $at, array $items): void;

    abstract protected function applyRemoved(int $at, int $count): void;

    abstract protected function applyUpdated(int $index, object $item): void;

    abstract protected function applyScrollTo(float $x, float $y): void;

    abstract protected function nativeScrollPosition(): ScrollPosition;
}
