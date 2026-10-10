<?php

namespace Surface\Contracts\Windows\Menus;

/**
 * A primitive's context menu: the items its toolkit opens where the primitive is right-clicked.
 * A chosen item posts the menu bar's MenuActivated with the item's id.
 */
interface ContextMenu
{
    public static function parse(array $nodes): ContextMenu;
}
