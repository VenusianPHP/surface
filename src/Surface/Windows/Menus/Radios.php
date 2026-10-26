<?php

namespace Surface\Windows\Menus;

/**
 * The radio state of a menu's items, shared by MenuProfile and ContextMenu.
 */
final class Radios
{
    /**
     * Group → the id of its item that is on.
     *
     * @param array<string, MenuItem> $items the menu's choosable items by id
     * @param array<string, list<string>> $groups group → item ids
     * @return array<string, string>
     */
    public static function onOf(array $items, array $groups): array
    {
        $on = [];
        foreach ($groups as $group => $members) {
            foreach ($members as $id) {
                if ($items[$id]->isOn()) {
                    $on[$group] = $id;
                }
            }
        }

        return $on;
    }
}