<?php

namespace Surface\Windows\Menus;

use Surface\Contracts\Windows\Menus\MenuRole;
use Surface\Contracts\Windows\WindowException;

readonly class MenuItem
{
    /**
     * @param string $id stable identity: the mail carries it; defaults to the label path slug
     * @param string|null $hotkey one character; each toolkit adds its primary modifier
     * @param list<MenuItem> $items children when this is a folder
     */
    private function __construct(
        public string $id,
        public string $label,
        public ?MenuRole $role,
        public bool $separator,
        public bool $toggle,
        public bool $on,
        public ?string $hotkey,
        public array $items,
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

        $hotkey = $node['hotkey'] ?? null;
        if ($hotkey !== null && strlen((string) $hotkey) !== 1) {
            throw new WindowException("Menu item '{$label}' hotkey must be one character.");
        }

        return new self($id, $label, $role, false, $toggle, (bool) ($node['on'] ?? false), $hotkey, $children);
    }

    private static function slug(string $path, string $label): string
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($label)));

        return $path === '' ? $slug : "{$path}.{$slug}";
    }
}