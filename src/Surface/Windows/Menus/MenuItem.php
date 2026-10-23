<?php

namespace Surface\Windows\Menus;

use Surface\Contracts\Windows\Menus\MenuRole;
use Surface\Contracts\Windows\WindowException;

readonly class MenuItem
{
    /**
     * @param string $id stable identity: the mail carries it; defaults to the label path slug
     * @param Accelerator|null $keys the accelerator, from `keys` or `hotkey`
     * @param list<MenuItem> $items children when this is a folder
     * @param bool $enabled the start state, from `enabled`
     */
    private function __construct(
        public string $id,
        public string $label,
        public ?MenuRole $role,
        public bool $separator,
        public bool $toggle,
        public bool $on,
        public ?Accelerator $keys,
        public array $items,
        public bool $enabled = true,
    ) {}

    public function isFolder(): bool
    {
        return $this->items !== [];
    }

    /**
     * @param array $node
     * @param string $path
     * @return self
     * @throws WindowException
     */
    public static function fromArray(array $node, string $path): self
    {
        if ($node['separator'] ?? false) {
            $other = array_diff(array_keys($node), ['separator']);
            if ($other !== []) {
                throw new WindowException("A menu separator takes no other keys: '".implode("', '", $other)."'.");
            }

            return new self('', '', null, true, false, false, null, []);
        }

        $label = (string) ($node['label'] ?? '');
        if ($label === '') {
            throw new WindowException('A menu node needs a label unless it is a separator.');
        }

        $id = (string) ($node['id'] ?? self::slug($path, $label));

        $role = null;
        if (isset($node['role'])) {
            $role = $node['role'] instanceof MenuRole ? $node['role'] : MenuRole::tryFrom((string) $node['role']);
            if ($role === null) {
                throw new WindowException("Menu item '{$label}' names an unknown role '{$node['role']}'.");
            }
        }

        $children = [];
        if (isset($node['items'])) {
            if (! is_array($node['items']) || $node['items'] === []) {
                throw new WindowException("Menu folder '{$label}' needs a non-empty items list.");
            }
            foreach ($node['items'] as $child) {
                $children[] = self::fromArray($child, $id);
            }
        }

        $toggle = (bool) ($node['toggle'] ?? false);
        if ($toggle && ($role !== null || $children !== [])) {
            throw new WindowException("Menu item '{$label}' cannot be a toggle and a role or folder.");
        }

        if (isset($node['hotkey']) && isset($node['keys'])) {
            throw new WindowException("Menu item '{$label}' has hotkey and keys: give one of them.");
        }
        if ((isset($node['hotkey']) || isset($node['keys'])) && $children !== []) {
            throw new WindowException("Menu folder '{$label}' cannot have an accelerator.");
        }
        if (array_key_exists('enabled', $node) && ! is_bool($node['enabled'])) {
            throw new WindowException("Menu item '{$label}' enabled must be true or false.");
        }
        if (array_key_exists('enabled', $node) && array_any($children, fn (MenuItem $child): bool => $child->holdsEditRole())) {
            throw new WindowException("Menu folder '{$label}' holds an edit role, so it takes no enabled: a disabled folder would stop the edit keys on AppKit.");
        }
        if ($role?->isEdit() && (isset($node['hotkey']) || isset($node['keys']) || array_key_exists('enabled', $node))) {
            throw new WindowException("Menu item '{$label}' is an edit role: the toolkit sets its keys and its state, so it takes no hotkey, keys or enabled.");
        }

        try {
            $keys = match (true) {
                ($node['keys'] ?? null) instanceof Accelerator => $node['keys'],
                isset($node['keys']) && is_string($node['keys']) => Accelerator::parse($node['keys']),
                isset($node['keys']) => throw new WindowException('keys must be a string or an Accelerator.'),
                isset($node['hotkey']) => Accelerator::hotkey((string) $node['hotkey']),
                default => null,
            };
        } catch (WindowException $e) {
            throw new WindowException("Menu item '{$label}': {$e->getMessage()}", 0, $e);
        }

        return new self($id, $label, $role, false, $toggle, (bool) ($node['on'] ?? false), $keys, $children, (bool) ($node['enabled'] ?? true));
    }

    /**
     * Whether this node or a node below it is an edit role.
     */
    public function holdsEditRole(): bool
    {
        return $this->role?->isEdit() === true || array_any($this->items, fn (MenuItem $item): bool => $item->holdsEditRole());
    }

    /**
     * The nodes and every node below them, by id, in tree order. Separators are not nodes.
     *
     * @param list<MenuItem> $items
     * @return array<string, MenuItem>
     * @throws WindowException when two nodes have one id
     */
    public static function nodesOf(array $items): array
    {
        $out = [];
        $walk = function (array $items) use (&$walk, &$out): void {
            foreach ($items as $item) {
                if ($item->separator) {
                    continue;
                }
                if (isset($out[$item->id])) {
                    throw new WindowException("nodes '{$out[$item->id]->label}' and '{$item->label}' have the same id '{$item->id}'.");
                }
                $out[$item->id] = $item;
                $walk($item->items);
            }
        };
        $walk($items);

        return $out;
    }

    private static function slug(string $path, string $label): string
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($label)));

        return $path === '' ? $slug : "{$path}.{$slug}";
    }
}