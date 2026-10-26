---
type: Reference
title: Menu profiles
description: config/windows.php menus - folders, items, roles, toggles, accelerators, enabled state, ids; primitives' context menus.
resource: src/Surface/Windows/Menus/
tags: [surface, menus, config]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-01T20:04:54Z }
sources:
  - id: item
    resource: src/Surface/Windows/Menus/MenuItem.php
    title: MenuItem
  - id: profile
    resource: src/Surface/Windows/Menus/MenuProfile.php
    title: MenuProfile
  - id: context
    resource: src/Surface/Windows/Menus/ContextMenu.php
    title: ContextMenu
  - id: accelerator
    resource: src/Surface/Windows/Menus/Accelerator.php
    title: Accelerator
  - id: states
    resource: src/Surface/Windows/Menus/MenuStates.php
    title: MenuStates
---

# Overview

`windows.menus` = named profiles. Profile = list of top-level folders (each must have `items`). Parsed once into readonly `MenuProfile` / `MenuItem`; bad config throws `WindowException` at parse.[^profile]

# Schema

| Key | Type | Rule |
|---|---|---|
| `label` | string | required unless separator |
| `id` | string | optional; default = parent id + `.` + label slug (`App` → `app`, `Quit` → `app.quit`); carried in mail; unique in the profile, folders included (throws) |
| `items` | list | folder; non-empty |
| `separator` | bool | `true` → separator, other keys ignored |
| `role` | `about` \| `quit` \| `undo` \| `redo` \| `cut` \| `copy` \| `paste` \| `select-all` (`MenuRole`) | system item; unknown role throws; an edit role takes no `hotkey`, `keys` or `enabled` (throws): the toolkit sets its keys and state |
| `toggle` | bool | checkable; not with role or items |
| `radio` | non-empty string | radio group name; not with `toggle`, `role` or `items`; takes `hotkey`, `keys` (bar only), `enabled`; a group sits in one folder, has exactly one item `on`, and is not an item id |
| `on` | bool | a toggle's or radio item's initial state; on any other node it throws |
| `keys` | string | accelerator, e.g. `Primary+Shift+F5`; items only; see Accelerators |
| `hotkey` | 1 char | = `keys` `Primary+<char>`; a character a key types without Shift, or an upper-case letter; not with `keys` |
| `enabled` | bool | start state, default `true`; items and folders; not on the first folder or a folder holding an edit role |

`MenuProfile::items()` = leaf items by id; `nodes()` = items and folders by id, tree order; `toggles()` = initial toggle states; `radios()` = group → the item starting on; `radioItems(group)` = its ids.[^item]

# Accelerators

`Accelerator::parse('Primary+Shift+F5')`: modifier tokens, then one key token, `+` between, any case.[^accelerator] Modifiers: `Primary` (Command on macOS, Control elsewhere), `Ctrl`, `Alt` (Option on macOS), `Shift`, `Meta` (Command on macOS, Super elsewhere); `command()` / `control()` / `super()` resolve them per platform. Keys: letters, digits, punctuation (`MINUS` … `SLASH`), `F1`-`F12`, arrows, `HOME`, `END`, `PAGE_UP`, `PAGE_DOWN`, `SPACE`, `ENTER`, `TAB`, `ESCAPE`, `BACKSPACE`, `DELETE`; by `Key` case name or by the character typed without Shift (`]` = `RIGHT_BRACKET`). Other keys throw (`INSERT`: AppKit cannot choose an item from the Help key a Mac reads for Insert). A key other than `F1`-`F12` needs `Primary`, `Ctrl`, `Alt` or `Meta`. Two items with the same accelerator on macOS or elsewhere throw; each edit role holds its standard one (`Primary+Z`, `Primary+Shift+Z`, `Primary+X`, `Primary+C`, `Primary+V`, `Primary+A`). An enabled accelerator gets its key before the focused widget, on all toolkits.

# Enabled state

`$window->setItemEnabled($id, false)` / `isItemEnabled($id)`; the default bar: `ToolkitWindowManager::setItemEnabled()` (gives the driver the default profile first). No rebuild, no mail. A node is effectively enabled when it and every folder above it are enabled; the rest show disabled, post nothing, and their accelerators do nothing. `isItemEnabled()` = own state; it throws, as `setItemEnabled()` does, for an edit role, a folder holding one and the first folder (the toolkit owns those). `MenuStates` holds both for drivers and fakes; `changes()` = the states that differ from the profile, which a bar built later replays.[^states] `enabled` must be a bool; `keys` a string or an `Accelerator`; a separator takes no other key. A new profile (`setMenuBar`, another default) starts from its own states.

# Radios and labels

`$window->setRadio($group, $item)` / `radio($group)`; `setItemLabel($id, $label)` / `itemLabel($id)` for any node but a separator, folders too. The window's bar only; posts nothing. The user's choice of another item posts `MenuRadioChosen(window, group, item)`; the item already on posts nothing. Default bar: `ToolkitWindowManager::setToggle()`, `isToggled()`, `setRadio()`, `radio()`, `setItemLabel()`, `itemLabel()` as for enabled. `MenuStates` keeps all four kinds; `toggleChanges()`, `radioChanges()`, `labelChanges()` (and `changes()` for enabled) = what a rebuilt bar replays. The same id or group in a bar and a context menu posts the same mail.

# Examples

```php
'menus' => [
    'main' => [
        ['label' => 'App', 'items' => [
            ['role' => 'about', 'label' => 'About'],
            ['separator' => true],
            ['role' => 'quit', 'label' => 'Quit', 'hotkey' => 'q'],
        ]],
        ['label' => 'View', 'items' => [
            ['id' => 'grid', 'label' => 'Show Grid', 'toggle' => true, 'on' => false],
            ['id' => 'refresh', 'label' => 'Refresh', 'hotkey' => 'r'],
        ]],
    ],
],
```

macOS renames the first folder to the process name; About and Quit go there.

Edit roles act on the focused text, or on a list for Select All: AppKit sends them to the first responder with the standard key equivalents; GTK and Qt run the focused widget's own action, and their text widgets keep their own keys. Surface's default `main` profile has an Edit folder; an app with its own profiles adds one to each profile its windows use.

# Context menus

`$primitive->setContextMenu($nodes)` = menu toolkit opens where primitive right-clicked. Nodes = item schema above minus `role`, `hotkey`, `keys` (each throws; empty list throws); `enabled`, toggles and radios are valid. The menu keeps the user's toggle and radio choices: `contextMenu()` returns it with them (`withToggle()` / `withRadio()` copies; `nativeContextToggled()` / `nativeContextRadio()` from the driver); a menu button's menu too. `popupMenu($nodes, $x, $y)` opens a menu now at a point in the primitive (points from its top-left); throws when removed or not shown; its choices post mail and are not kept. Takes parsed `ContextMenu::parse($nodes)` too (one menu, many primitives); `null` = off. `contextMenu()` reads it. Ids default to label slug from the top (`Open` → `open`, `Share` › `Copy Link` → `share.copy-link`).[^context]

Right click → `view.right-clicked` first, then menu of clicked primitive or nearest group above with one. Choice → `MenuActivated` (`menu.activated.<window>.<id>`); dismiss → nothing. Disabled → no mail, no menu. Primitive with menu shows only it (text input's own edit menu does not open). Bare window content has none.

```php
$image->setContextMenu([
    ['id' => 'download', 'label' => 'Download'],
    ['id' => 'open', 'label' => 'Open in a Window'],
]);
// mail: menu.activated.main.download
```

[^item]: MenuItem
[^profile]: MenuProfile
[^context]: ContextMenu
[^accelerator]: Accelerator
[^states]: MenuStates
