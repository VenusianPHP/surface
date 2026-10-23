<?php

namespace Surface\Windows\Menus;

use Surface\Contracts\Windows\WindowException;

/**
 * The enabled state of each node of one menu: its own state, from `enabled` and then from
 * set(), and its effective state. A node is effectively enabled when it is enabled and every
 * folder above it is enabled. Edit roles, folders that hold an edit role and the first folder
 * of a menu profile take no state.
 */
final class MenuStates
{
    /** @var array<string, MenuItem> */
    private readonly array $nodes;

    /** @var array<string, string|null> the folder above each node */
    private readonly array $parents;

    /** @var array<string, bool> */
    private array $own = [];

    private readonly ?string $first;

    public function __construct(MenuProfile|ContextMenu $menu)
    {
        $this->nodes = $menu->nodes();
        $parents = [];
        $walk = function (array $items, ?string $parent) use (&$walk, &$parents): void {
            foreach ($items as $item) {
                if (! $item->separator) {
                    $parents[$item->id] = $parent;
                    $walk($item->items, $item->id);
                }
            }
        };
        $walk($menu instanceof MenuProfile ? $menu->folders : $menu->items, null);
        $this->parents = $parents;
        $this->first = $menu instanceof MenuProfile ? ($menu->folders[0]->id ?? null) : null;
        foreach ($this->nodes as $id => $node) {
            $this->own[$id] = $node->enabled;
        }
    }

    /**
     * @throws WindowException when no node has the id, or the node cannot take a state
     */
    public function set(string $id, bool $enabled): void
    {
        $this->guard($id);
        $this->own[$id] = $enabled;
    }

    /** @throws WindowException when no node has the id, or the node takes no state (the toolkit owns it) */
    public function isEnabled(string $id): bool
    {
        $this->guard($id);

        return $this->own[$id];
    }

    /** @throws WindowException when no node has the id */
    public function effective(string $id): bool
    {
        $this->node($id);
        for ($at = $id; ! is_null($at); $at = $this->parents[$at]) {
            if (! $this->own[$at]) {
                return false;
            }
        }

        return true;
    }

    /**
     * The id, then the id of every node below it, in tree order.
     *
     * @return list<string>
     * @throws WindowException when no node has the id
     */
    public function subtree(string $id): array
    {
        return array_keys(MenuItem::nodesOf([$this->node($id)]));
    }

    /**
     * The own states that differ from the profile, by id, in tree order: what a bar built later replays.
     *
     * @return array<string, bool>
     */
    public function changes(): array
    {
        return array_filter(array_map(fn (MenuItem $node): ?bool => $this->own[$node->id] === $node->enabled ? null : $this->own[$node->id], $this->nodes), fn (?bool $state): bool => ! is_null($state));
    }

    /** @throws WindowException when no node has the id, or the node takes no state */
    private function guard(string $id): void
    {
        $node = $this->node($id);
        if ($node->holdsEditRole()) {
            throw new WindowException("Menu node '{$node->label}' is or holds an edit role: the toolkit sets its state.");
        }
        if ($id === $this->first) {
            throw new WindowException("Menu folder '{$node->label}' is the first folder: on macOS it is the application menu, so it takes no state.");
        }
    }

    private function node(string $id): MenuItem
    {
        return $this->nodes[$id] ?? throw new WindowException("No menu node has the id '{$id}'.");
    }
}
