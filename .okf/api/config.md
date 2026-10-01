---
type: Reference
title: Config
description: config/bridge.php and config/windows.php keys.
resource: config/
tags: [surface, config]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-01T20:04:54Z }
sources:
  - id: bridge
    resource: config/bridge.php
    title: config/bridge.php
  - id: windows
    resource: config/windows.php
    title: config/windows.php
---

# Schema

`bridge`:[^bridge]

| Key | Default | Values |
|---|---|---|
| `toolkit.mac.default` | `appkit` | `appkit`, `gtk`, `qt` |
| `toolkit.linux.default` | `gtk` | `gtk`, `qt` |

Driver keys, read with code defaults by each driver (absent from the published file): `bridge.gtk.application_id`, `bridge.gtk.unique`, `bridge.qt.application_name`, `bridge.qt.desktop_file_name`. See each jovian driver bundle.

`windows`:[^windows]

| Key | Default | Meaning |
|---|---|---|
| `about.name` | `env('APP_NAME', 'Venusian')` | About title |
| `about.version` | `env('APP_VERSION')` | About version line |
| `about.copyright` | `null` | About copyright line |
| `default_menu` | `main` | profile for the macOS default bar; `null` = empty bar |
| `menus` | `main` profile | [menu profiles](/api/menu-profiles.md) |

[^bridge]: config/bridge.php
[^windows]: config/windows.php
