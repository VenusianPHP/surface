---
type: Module
title: Components
description: Splits, providers, container bindings, published configs.
resource: composer.json
tags: [surface, composer, providers]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-01T20:04:54Z }
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
---

# Overview

`venusian/surface` (root, version key `0.10.0`) `replace`s every split at `self.version`. Splits with a manifest this line:[^root]

| Split | Namespace | Holds | Requires |
|---|---|---|---|
| `venusian-surface/contracts` | `Surface\Contracts\` | Interfaces, mail, `MenuRole`, exceptions | voyager contracts |
| `venusian-surface/bridge` | `Surface\Bridge\` | `ToolkitManager`, `ToolkitBridgeDriver`, `BridgedToolkitSession`, `ToolkitPump` | contracts, `venusian-voyager/io-pools` |
| `venusian-surface/windows` | `Surface\Windows\` | `ToolkitWindowManager`, `MenuProfile`, `MenuItem` | contracts, bridge |

Toolkit drivers live outside: `jovian/venusian-appkit`, `-gtk`, `-qt`. A split never requires `venusian/surface`; only an app composes.

# Providers

* Root discovers `Surface\Core\Providers\SurfaceServiceProvider` (aggregate): merges `config/bridge.php` → `bridge`, `config/windows.php` → `windows`; registers Windows + Bridge providers; publishes both configs under tag `surface-config`.[^core]
* `BridgeServiceProvider`: singleton `toolkit-bridge` = `ToolkitManager`, alias `ToolkitManager::class`.[^bridge]
* `WindowsServiceProvider`: singleton `toolkit-windows` = `ToolkitWindowManager(toolkit-bridge, config('windows.menus'), config('windows.default_menu'))`, alias `ToolkitWindowManager::class`. Profiles parsed on first resolve.[^windows]
* Each driver package's provider binds its own contract to `toolkit-bridge`'s `driver('<name>')`: one driver, one session per process.

```bash
php computer vendor:publish --tag=surface-config
```

[^root]: composer.json
[^core]: SurfaceServiceProvider
[^bridge]: BridgeServiceProvider
[^windows]: WindowsServiceProvider
