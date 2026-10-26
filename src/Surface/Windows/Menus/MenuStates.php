<?php

namespace Surface\Windows\Menus;

use Surface\Contracts\Windows\WindowException;

/**
 * The state of one menu's nodes. Enabled: each node's own state, from `enabled` and then from
 * set(), and its effective state; a node is effectively enabled when it is enabled and every
 * folder above it is enabled; edit roles, folders that hold an edit role and the first folder of
 * a menu profile take no state. Toggles, the item on in each radio group, and the label of every
 * node, from the menu and then from their setters. Each kind's changes() is what differs from the
 * menu: what a bar built later replays.
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

    /** @var array<string, bool> toggle id → on */
    private array $toggles = [];

    /** @var array<string, string> group → the item on */
    private array $radios = [];

    /** @var array<string, list<string>> group → item ids */
    private readonly array $groups;

    /** @var array<string, string> node id → label */
    private array $labels = [];

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
            $this->labels[$id] = $node->label;
            if ($node->toggle) {
                $this->toggles[$id] = $node->on;
            }
        }
        $this->groups = MenuItem::radioGroupsOf($menu instanceof MenuProfile ? $menu->folders : $menu->items);
        $this->radios = $menu->radios();
    }

    /** @throws WindowException when no toggle item has the id */
    public function toggle(string $item): bool
    {
        return $this->toggles[$item] ?? throw self::noToggle($item);
    }

    /** @throws WindowException when no toggle item has the id */
    public function setToggle(string $item, bool $on): void
    {
        $this->toggle($item);
        $this->toggles[$item] = $on;
    }

    /** The item on in $group. @throws WindowException when no group has the name */
    public function radio(string $group): string
    {
        return $this->radios[$group] ?? throw new WindowException("No radio group '{$group}' in the menu bar.");
    }

    /** @throws WindowException when no group has the name, or the item is not one of it */
    public function setRadio(string $group, string $item): void
    {
        $this->radio($group);
        if (! in_array($item, $this->groups[$group], true)) {
            throw new WindowException("'{$item}' is not in radio group '{$group}'.");
        }
        $this->radios[$group] = $item;
    }

    /** @throws WindowException when no node has the id (a separator has none) */
    public function label(string $id): string
    {
        $this->node($id);

        return $this->labels[$id];
    }

    /** @throws WindowException when no node has the id (a separator has none) */
    public function setLabel(string $id, string $label): void
    {
        $this->node($id);
        $this->labels[$id] = $label;
    }

    /** @return array<string, bool> the toggles that differ from the menu, by id, in tree order */
    public function toggleChanges(): array
    {
        return array_filter($this->toggles, fn (bool $on, string $id): bool => $on !== $this->nodes[$id]->on, ARRAY_FILTER_USE_BOTH);
    }

    /** @return array<string, string> the groups whose item on differs from the menu's */
    public function radioChanges(): array
    {
        return array_filter($this->radios, fn (string $item): bool => ! $this->nodes[$item]->on);
    }

    /** @return array<string, string> the labels that differ from the menu, by id, in tree order */
    public function labelChanges(): array
    {
        return array_filter($this->labels, fn (string $label, string $id): bool => $label !== $this->nodes[$id]->label, ARRAY_FILTER_USE_BOTH);
    }

    private static function noToggle(string $item): WindowException
    {
        return new WindowException("No toggle item '{$item}' in the menu.");
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
     * The own enabled states that differ from the profile, by id, in tree order: what a bar built later replays.
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
