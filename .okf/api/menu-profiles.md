---
type: Reference
title: Menu profiles
description: config/windows.php menus - folders, items, roles, toggles, hotkeys, ids; primitives' context menus.
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
---

# Overview

`windows.menus` = named profiles. Profile = list of top-level folders (each must have `items`). Parsed once into readonly `MenuProfile` / `MenuItem`; bad config throws `WindowException` at parse.[^profile]

# Schema

| Key | Type | Rule |
|---|---|---|
| `label` | string | required unless separator |
| `id` | string | optional; default = parent id + `.` + label slug (`App` → `app`, `Quit` → `app.quit`); carried in mail |
| `items` | list | folder; non-empty |
| `separator` | bool | `true` → separator, other keys ignored |
| `role` | `about` \| `quit` (`MenuRole`) | system item; unknown role throws |
| `toggle` | bool | checkable; not with role or items |
| `on` | bool | toggle's initial state |
| `hotkey` | 1 char | toolkit adds its primary modifier (Command on macOS, Control on Linux) |

`MenuProfile::items()` = leaf items by id; `toggles()` = initial toggle states.[^item]

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

# Context menus

`$primitive->setContextMenu($nodes)` = menu toolkit opens where primitive right-clicked. Nodes = item schema above minus `role`, `toggle`, `hotkey` (each throws; empty list throws). Takes parsed `ContextMenu::parse($nodes)` too (one menu, many primitives); `null` = off. `contextMenu()` reads it. Ids default to label slug from the top (`Open` → `open`, `Share` › `Copy Link` → `share.copy-link`).[^context]

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
