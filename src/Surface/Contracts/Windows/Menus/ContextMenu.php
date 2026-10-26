<?php

namespace Surface\Contracts\Windows\Menus;

/**
 * A primitive's context menu: the items its toolkit opens where the primitive is right-clicked,
 * or where popupMenu() asks. A chosen item posts the menu bar's MenuActivated with the item's id;
 * a toggle MenuToggled, a radio item MenuRadioChosen, both with the menu's window.
 */
interface ContextMenu
{
    public static function parse(array $nodes): ContextMenu;
}
