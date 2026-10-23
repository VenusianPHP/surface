<?php

namespace Surface\Windows;

use Surface\Contracts\Windows\Menus\MenuProfile;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\ToolkitWindowDriver;
use Surface\Contracts\Windows\WindowException;

/**
 * The parent rules of a child window, one time for every driver: a parent is an open top-level
 * window of the same driver with no open child, and a child has no menu bar.
 */
final class ChildWindows
{
    /**
     * @throws WindowException When the parent is closed, is not a window of $driver, is a child,
     *                         or has an open child, or $menu is not null.
     */
    public static function check(ToolkitWindowDriver $driver, ToolkitWindow $parent, ?MenuProfile $menu): void
    {
        $name = $parent->name();
        if (! $parent->isOpen()) {
            throw new WindowException("Window '{$name}' is closed, so it cannot be a parent.");
        }
        if ($driver->get($name) !== $parent) {
            throw new WindowException("Window '{$name}' is not a window of this driver, so it cannot be a parent.");
        }
        if (! is_null($parent->parent())) {
            throw new WindowException("Window '{$name}' is a child, so it cannot be a parent.");
        }
        $child = self::childOf($driver, $parent);
        if (! is_null($child)) {
            throw new WindowException("Window '{$name}' already has the open child '{$child->name()}'.");
        }
        if (! is_null($menu)) {
            throw new WindowException("A child of window '{$name}' cannot have a menu bar.");
        }
    }

    /** @throws WindowException When $window is a child: a child has no menu bar. */
    public static function refuseMenuBar(ToolkitWindow $window): void
    {
        if (! is_null($window->parent())) {
            throw new WindowException("Window '{$window->name()}' is a child, so it cannot have a menu bar.");
        }
    }

    /** The open window of $driver whose parent is $parent, or null. */
    public static function childOf(ToolkitWindowDriver $driver, ToolkitWindow $parent): ?ToolkitWindow
    {
        foreach ($driver->all() as $window) {
            if ($window->parent() === $parent && $window->isOpen()) {
                return $window;
            }
        }

        return null;
    }
}
