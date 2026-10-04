---
type: Reference
title: Config
description: config/bridge.php, config/windows.php, config/framebuffers.php, config/rasterize.php, config/images.php and config/drawing.php keys.
resource: config/
tags: [surface, config]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-04T01:28:52Z }
sources:
  - id: bridge
    resource: config/bridge.php
    title: config/bridge.php
  - id: windows
    resource: config/windows.php
    title: config/windows.php
  - id: framebuffers
    resource: config/framebuffers.php
    title: config/framebuffers.php
  - id: rasterize
    resource: config/rasterize.php
    title: config/rasterize.php
  - id: images
    resource: config/images.php
    title: config/images.php
  - id: drawing
    resource: config/drawing.php
    title: config/drawing.php
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

`framebuffers`:[^framebuffers]

| Key | Default | Values |
|---|---|---|
| `default` | `env('FRAMEBUFFERS_DRIVER', 'auto')` | `auto` (extended when ext-fb 0.10 is loaded, native when not), `native` (bytes in PHP, always available), `extended` (bytes in C, needs ext-fb 0.10) |

`rasterize`:[^rasterize]

| Key | Default | Values |
|---|---|---|
| `default` | `env('RASTERIZE_DRIVER', 'auto')` | `auto` (extended when ext-rasterize is loaded, native when not), `native` (geometry in PHP, always available), `extended` (geometry in C, needs ext-rasterize 0.10) |

`images`:[^images]

| Key | Default | Values |
|---|---|---|
| `default` | `env('IMAGES_DRIVER', 'auto')` | `auto` (extended when ext-imgdec is loaded, native when not), `native` (PNG/JPEG through ext-gd, TIFF in PHP), `extended` (all three in C, needs ext-imgdec 0.10) |
| `framebuffers` | `null` | framebuffer driver decoded images live on; `null` = `framebuffers.default` |

`drawing`:[^drawing]

| Key | Default | Values |
|---|---|---|
| `default` | `velvet` | the engine `renderer()` builds when none is named: `velvet` (software, built in), or one an engine package registers |

[^bridge]: config/bridge.php
[^windows]: config/windows.php
[^framebuffers]: config/framebuffers.php
[^rasterize]: config/rasterize.php
[^images]: config/images.php
[^drawing]: config/drawing.php
