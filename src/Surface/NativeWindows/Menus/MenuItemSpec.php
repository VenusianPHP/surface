<?php

namespace Surface\NativeWindows\Menus;

use Surface\Contracts\NativeWindows\WindowableException;
use Surface\NativeWindows\Enums\MenuRole;

/**
 * One parsed node of a menu-bar profile, engine-neutral.
 *
 * Profiles are written as plain arrays in a sketch and normalised here once,
 * at registration, so both engine packages receive the same validated tree
 * and neither ever parses sketch input. One key decides what a node is:
 * 'items' makes a folder, 'role' a native behaviour, 'event' a named
 * SurfaceEvent the sketch loop drains, 'separator' a separator. Nothing
 * user-authored executes inside a pump — engines only push.
 */
final class MenuItemSpec
{
    /**
     * @param string $id Stable identity for the item; defaults to the label path.
     * @param string $label Text the OS renders. Empty only for separators.
     * @param MenuRole|null $role Native behaviour the engine translates, or null.
     * @param string|null $event Name pushed as a MENU SurfaceEvent on activation, or null.
     * @param string|null $hotkey Bare character; the engine adds its platform's primary modifier.
     * @param bool $separator Whether this node is a separator.
     * @param list<MenuItemSpec> $items Children when this node is a folder.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly ?MenuRole $role,
        public readonly ?string $event,
        public readonly ?string $hotkey,
        public readonly bool $separator,
        public readonly array $items,
    ) {}

    /**
     * Whether this node is a folder holding child items.
     * @return bool
     */
    public function isFolder(): bool
    {
        return count($this->items) > 0;
    }

    /**
     * Parse one profile definition into a validated spec tree.
     *
     * @param array $nodes The profile as written in the sketch.
     * @param string $path Id prefix carried down the recursion.
     * @return list<MenuItemSpec>
     * @throws WindowableException When a node is neither folder, role, event, nor separator, or has no label.
     */
    public static function parseList(array $nodes, string $path = ''): array
    {
        $specs = [];
        foreach ($nodes as $node) {
            $specs[] = self::fromArray($node, $path);
        }

        return $specs;
    }

    /**
     * Parse a single node.
     *
     * @param array $node One node of the profile definition.
     * @param string $path Id prefix from the parent folder.
     * @return MenuItemSpec
     * @throws WindowableException When the node is malformed.
     */
    public static function fromArray(array $node, string $path = ''): MenuItemSpec
    {
        if ($node['separator'] ?? false) {
            return new self('', '', null, null, null, true, []);
        }

        $label = $node['label'] ?? '';
        if ($label === '') {
            throw new WindowableException('Menu node has no label and is not a separator.');
        }

        $id = $node['id'] ?? self::slugPath($path, $label);

        $role = $node['role'] ?? null;
        if (! is_null($role) && ! ($role instanceof MenuRole)) {
            $role = MenuRole::tryFrom((string) $role);
            if (is_null($role)) {
                throw new WindowableException("Menu node '{$label}' names an unknown role.");
            }
        }

        $event = $node['event'] ?? null;
        if (! is_null($event)) {
            if (! is_string($event) || $event === '') {
                throw new WindowableException("Menu node '{$label}' has an event that is not a non-empty string.");
            }
        }

        $is_folder = isset($node['items']);
        $children = [];
        if ($is_folder) {
            if (! is_array($node['items'])) {
                throw new WindowableException("Menu folder '{$label}' has items that are not an array.");
            }
            $children = self::parseList($node['items'], $id);
        }

        if (is_null($role) && is_null($event) && ! $is_folder) {
            throw new WindowableException("Menu node '{$label}' is neither a folder, a role, nor an event.");
        }

        return new self(
            id: $id,
            label: $label,
            role: $role,
            event: $event,
            hotkey: $node['hotkey'] ?? null,
            separator: false,
            items: $children,
        );
    }

    /**
     * Derive a stable id from the label path when the sketch supplies none.
     * @param string $path Parent id, empty at the top level.
     * @param string $label This node's label.
     * @return string
     */
    protected static function slugPath(string $path, string $label): string
    {
        $slug = strtolower(str_replace(' ', '-', trim($label)));

        return $path === '' ? $slug : "{$path}.{$slug}";
    }
}
