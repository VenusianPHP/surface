<?php

namespace Surface\Windows\Menus;

use Closure;
use Surface\Contracts\Windows\Menus\ContextMenu as ContextMenuContract;
use Surface\Contracts\Windows\WindowException;

/**
 * A context menu's items, in a menu profile's item format: labels, ids, separators, submenus,
 * toggles and radio groups. No roles or accelerators: About and Quit live in the bar, and a context
 * menu has no accelerator table. withToggle() and withRadio() give copies with the user's choice.
 */
readonly class ContextMenu implements ContextMenuContract
{
    /**
     * @param list<MenuItem> $items
     */
    private function __construct(
        public array $items,
    ) {}

    /**
     * @param array $nodes
     * @return self
     * @throws WindowException
     */
    public static function parse(array $nodes): self
    {
        if ($nodes === []) {
            throw new WindowException('A context menu needs at least one item.');
        }

        $items = array_map(fn (array $node): MenuItem => MenuItem::fromArray($node, ''), array_values($nodes));
        self::guard($items);
        try {
            MenuItem::nodesOf($items);
            MenuItem::radioGroupsOf($items);
        } catch (WindowException $e) {
            throw new WindowException("Context menu: {$e->getMessage()}", 0, $e);
        }

        return new self($items);
    }

    /**
     * The radio groups and the item each is on: group → item id.
     *
     * @return array<string, string>
     */
    public function radios(): array
    {
        return Radios::onOf($this->items(), MenuItem::radioGroupsOf($this->items));
    }

    /**
     * A radio group's item ids, in tree order.
     *
     * @return list<string>
     * @throws WindowException When no radio group has the name.
     */
    public function radioItems(string $group): array
    {
        return MenuItem::radioGroupsOf($this->items)[$group] ?? throw new WindowException("No radio group '{$group}' in the menu.");
    }

    /**
     * A copy with the toggle $item set to $on.
     *
     * @throws WindowException When no toggle item has the id.
     */
    public function withToggle(string $item, bool $on): static
    {
        if (! ($this->items()[$item] ?? null)?->toggle) {
            throw new WindowException("No toggle item '{$item}' in the menu.");
        }

        return new static(self::mapItems($this->items, fn (MenuItem $node): MenuItem => $node->id === $item ? $node->withOn($on) : $node));
    }

    /**
     * A copy with $item the one on in its radio group.
     *
     * @throws WindowException When no group has the name, or the item is not one of it.
     */
    public function withRadio(string $group, string $item): static
    {
        $members = $this->radioItems($group);
        if (! in_array($item, $members, true)) {
            throw new WindowException("'{$item}' is not in radio group '{$group}'.");
        }

        return new static(self::mapItems($this->items, fn (MenuItem $node): MenuItem => $node->radio() === $group ? $node->withOn($node->id === $item) : $node));
    }

    /**
     * @param list<MenuItem> $items
     * @param Closure(MenuItem): MenuItem $map
     * @return list<MenuItem>
     */
    private static function mapItems(array $items, Closure $map): array
    {
        return array_map(fn (MenuItem $item): MenuItem => $item->separator ? $item : $map($item->isFolder() ? $item->withItems(self::mapItems($item->items, $map)) : $item), $items);
    }

    /**
     * Every item and every folder, by id, in tree order. Separators are not nodes.
     * @return array<string, MenuItem>
     */
    public function nodes(): array
    {
        return MenuItem::nodesOf($this->items);
    }

    /**
     * Every choosable item, keyed by id.
     * @return array<string, MenuItem>
     */
    public function items(): array
    {
        $out = [];
        $walk = function (array $items) use (&$walk, &$out): void {
            foreach ($items as $item) {
                if ($item->isFolder()) {
                    $walk($item->items);
                } elseif (! $item->separator) {
                    $out[$item->id] = $item;
                }
            }
        };
        $walk($this->items);

        return $out;
    }

    /**
     * @param list<MenuItem> $items
     * @throws WindowException
     */
    private static function guard(array $items): void
    {
        foreach ($items as $item) {
            if (! is_null($item->role) || ! is_null($item->keys)) {
                throw new WindowException("Context menu item '{$item->label}': a context menu item cannot be a role or carry an accelerator.");
            }
            self::guard($item->items);
        }
    }
}
