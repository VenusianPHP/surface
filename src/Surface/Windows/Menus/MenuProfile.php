<?php

namespace Surface\Windows\Menus;

use Surface\Contracts\Windows\WindowException;
use Surface\Contracts\Windows\Menus\MenuProfile as MenuProfileContract;

readonly class MenuProfile implements MenuProfileContract
{
    /**
     * @param string $name
     * @param list<MenuItem> $folders top-level bar entries, every one a folder
     */
    private function __construct(
        public string $name,
        public array $folders,
    ) {}

    /**
     * @param string $name
     * @param array $nodes
     * @return self
     * @throws WindowException
     */
    public static function parse(string $name, array $nodes): self
    {
        $folders = [];
        foreach ($nodes as $node)
        {
            $folder = MenuItem::fromArray($node, '');
            if (! $folder->isFolder())
            {
                throw new WindowException("Menu profile '{$name}': top-level entry '{$folder->label}' must be a folder.");
            }
            $folders[] = $folder;
        }

        return new self($name, $folders);
    }

    /**
     * Every non-folder, non-separator item, keyed by id. @return array<string, MenuItem>
     *
     * @return array
     */
    public function items(): array
    {
        $out = [];
        $walk = function (array $items) use (&$walk, &$out): void
        {
            foreach ($items as $item)
            {
                if ($item->isFolder())
                {
                    $walk($item->items);
                }
                elseif (! $item->separator)
                {
                    $out[$item->id] = $item;
                }
            }
        };

        $walk($this->folders);

        return $out;
    }

    /**
     * Initial toggle states.
     *
     * @return array<string, bool>
     */
    public function toggles(): array
    {
        return array_map(fn (MenuItem $i): bool => $i->on, array_filter($this->items(), fn (MenuItem $i): bool => $i->toggle));
    }
}