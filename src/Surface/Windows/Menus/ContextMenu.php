<?php

namespace Surface\Windows\Menus;

use Surface\Contracts\Windows\Menus\ContextMenu as ContextMenuContract;
use Surface\Contracts\Windows\WindowException;

/**
 * A context menu's items, in a menu profile's item format: labels, ids, separators and
 * submenus. No toggles, roles or hotkeys: About and Quit live in the bar, and a context menu
 * has no accelerator table.
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

        return new self($items);
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
            if ($item->toggle || ! is_null($item->role) || ! is_null($item->hotkey)) {
                throw new WindowException("Context menu item '{$item->label}' cannot be a toggle, a role or carry a hotkey.");
            }
            self::guard($item->items);
        }
    }
}
