---
type: Reference
title: Menu profiles
description: config/windows.php menus - folders, items, roles, toggles, hotkeys, ids.
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

[^item]: MenuItem
[^profile]: MenuProfile
