---
type: Module
title: Components
description: Splits, providers, container bindings, published configs.
resource: composer.json
tags: [surface, composer, providers]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-03T23:06:29Z }
sources:
  - id: root
    resource: composer.json
    title: composer.json
  - id: core
    resource: src/Surface/Core/Providers/SurfaceServiceProvider.php
    title: SurfaceServiceProvider
  - id: bridge
    resource: src/Surface/Bridge/BridgeServiceProvider.php
    title: BridgeServiceProvider
  - id: windows
    resource: src/Surface/Windows/WindowsServiceProvider.php
    title: WindowsServiceProvider
  - id: framebuffers
    resource: src/Surface/Framebuffers/FramebuffersServiceProvider.php
    title: FramebuffersServiceProvider
  - id: rasterize
    resource: src/Surface/Rasterize/RasterizeServiceProvider.php
    title: RasterizeServiceProvider
---

# Overview

`venusian/surface` (root, version key `0.10.0`) `replace`s every split at `self.version`. Splits with a manifest this line:[^root]

| Split | Namespace | Holds | Requires |
|---|---|---|---|
| `venusian-surface/nuts-and-bolts` | `Surface\NutsAndBolts\` | `Color` | php only |
| `venusian-surface/contracts` | `Surface\Contracts\` | Interfaces, mail, `MenuRole`, exceptions, primitive contracts, `Placement`, `PrimitiveRegistry`, typography values | voyager contracts, nuts-and-bolts |
| `venusian-surface/bridge` | `Surface\Bridge\` | `ToolkitManager`, `ToolkitBridgeDriver`, `BridgedToolkitSession`, `ToolkitPump` | contracts, `venusian-voyager/io-pools` |
| `venusian-surface/windows` | `Surface\Windows\` | `ToolkitWindowManager`, `MenuProfile`, `MenuItem`, primitive abstracts, `HostsPrimitives` | contracts, bridge, nuts-and-bolts |
| `venusian-surface/framebuffers` | `Surface\Framebuffers\` | `FramebufferManager`, `Layout`, `PixelMapper`, `SpanList`, the five abstract kinds, `Native\*` (bytes in PHP), `Extended\*` (bytes in C) | contracts, nuts-and-bolts, voyager nuts-and-bolts; suggests ext-fb 0.10 |
| `venusian-surface/rasterize` | `Surface\Rasterize\` | `RasterizeManager`, `Rasterizer` (shapes), `Stroker`, `Native\*` (geometry in PHP), `Extended\*` (geometry in C) | contracts, voyager contracts + nuts-and-bolts; suggests ext-rasterize 0.10 |

Toolkit drivers live outside: `jovian/venusian-appkit`, `-gtk`, `-qt`. A split never requires `venusian/surface`; only an app composes.

# Providers

* Root discovers `Surface\Core\Providers\SurfaceServiceProvider` (aggregate): merges `config/bridge.php` → `bridge`, `config/windows.php` → `windows`, `config/framebuffers.php` → `framebuffers`, `config/rasterize.php` → `rasterize`; registers Windows, Bridge, Framebuffers and Rasterize providers; publishes the four configs under tag `surface-config`.[^core]
* `BridgeServiceProvider`: singleton `toolkit-bridge` = `ToolkitManager`, alias `ToolkitManager::class`.[^bridge]
* `WindowsServiceProvider`: singleton `toolkit-windows` = `ToolkitWindowManager(toolkit-bridge, config('windows.menus'), config('windows.default_menu'))`, alias `ToolkitWindowManager::class`. Profiles parsed on first resolve.[^windows]
* `FramebuffersServiceProvider`: singleton `framebuffers` = `FramebufferManager`, alias `FramebufferManager::class`; `driver('native')` always, `driver('extended')` with ext-fb 0.10.[^framebuffers]
* `RasterizeServiceProvider`: singleton `rasterize` = `RasterizeManager`, alias `RasterizeManager::class`; `driver('native')` always, `driver('extended')` with ext-rasterize 0.10.[^rasterize]
* Each driver package's provider binds its own contract to `toolkit-bridge`'s `driver('<name>')`: one driver, one session per process.

```bash
php computer vendor:publish --tag=surface-config
```

[^root]: composer.json
[^core]: SurfaceServiceProvider
[^bridge]: BridgeServiceProvider
[^windows]: WindowsServiceProvider
[^framebuffers]: FramebuffersServiceProvider
[^rasterize]: RasterizeServiceProvider
