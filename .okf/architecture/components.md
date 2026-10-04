---
type: Module
title: Components
description: Splits, providers, container bindings, published configs.
resource: composer.json
tags: [surface, composer, providers]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-04T03:04:28Z }
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
  - id: images
    resource: src/Surface/Images/ImagesServiceProvider.php
    title: ImagesServiceProvider
  - id: embedded-displays
    resource: src/Surface/EmbeddedDisplays/EmbeddedDisplaysServiceProvider.php
    title: EmbeddedDisplaysServiceProvider
  - id: rasterize
    resource: src/Surface/Rasterize/RasterizeServiceProvider.php
    title: RasterizeServiceProvider
  - id: drawing
    resource: src/Surface/Drawing/DrawingServiceProvider.php
    title: DrawingServiceProvider
---

# Overview

`venusian/surface` (root, version key `0.10.0`) `replace`s every split at `self.version`. Splits with a manifest this line:[^root]

| Split | Namespace | Holds | Requires |
|---|---|---|---|
| `venusian-surface/nuts-and-bolts` | `Surface\NutsAndBolts\` | `Color`, `Affine` | php only |
| `venusian-surface/contracts` | `Surface\Contracts\` | Interfaces, mail, `MenuRole`, exceptions, primitive contracts, `Placement`, `PrimitiveRegistry`, typography values | voyager contracts, nuts-and-bolts |
| `venusian-surface/bridge` | `Surface\Bridge\` | `ToolkitManager`, `ToolkitBridgeDriver`, `BridgedToolkitSession`, `ToolkitPump` | contracts, `venusian-voyager/io-pools` |
| `venusian-surface/windows` | `Surface\Windows\` | `ToolkitWindowManager`, `MenuProfile`, `MenuItem`, primitive abstracts (incl. `TKCanvas`), `HostsPrimitives` | contracts, bridge, framebuffers, nuts-and-bolts |
| `venusian-surface/framebuffers` | `Surface\Framebuffers\` | `FramebufferManager`, `Layout`, `PixelMapper`, `SpanList`, the five abstract kinds, `Native\*` (bytes in PHP), `Extended\*` (bytes in C) | contracts, nuts-and-bolts, voyager nuts-and-bolts; suggests ext-fb 0.10 |
| `venusian-surface/drawing` | `Surface\Drawing\` | `DrawingManager`, `RenderingEngine` (base), `Velvet\VelvetGE` | contracts, framebuffers, rasterize, nuts-and-bolts, voyager contracts + nuts-and-bolts; suggests the four engine packages |
| `venusian-surface/embedded-displays` | `Surface\EmbeddedDisplays\` | `EmbeddedDisplayManager`, `EmbeddedDisplay` | gpio/contracts, contracts, framebuffers, voyager contracts + nuts-and-bolts; suggests scrapyard-io/framework and the 0.10 panel chips |
| `venusian-surface/fonts` | `Surface\Fonts\` | `FontManager`, `ClassicFont`, `Console\FontMakeCommand`, `Support\AdafruitGfxHeader` | contracts, console, filesystem |
| `venusian-surface/images` | `Surface\Images\` | `ImagesManager`, `ImageDecoder` (base), `TiffDirectory`, `Native\*` (gd + PHP TIFF), `Extended\*` (C) | contracts, framebuffers, voyager contracts + nuts-and-bolts; suggests ext-gd, ext-imgdec 0.10 |
| `venusian-surface/rasterize` | `Surface\Rasterize\` | `RasterizeManager`, `Rasterizer` (shapes), `Stroker`, `Native\*` (geometry in PHP), `Extended\*` (geometry in C) | contracts, voyager contracts + nuts-and-bolts; suggests ext-rasterize 0.10 |

Toolkit drivers live outside: `jovian/venusian-appkit`, `-gtk`, `-qt`. A split never requires `venusian/surface`; only an app composes.

# Providers

* Root discovers `Surface\Core\Providers\SurfaceServiceProvider` (aggregate): merges `config/bridge.php` → `bridge`, `config/windows.php` → `windows`, `config/framebuffers.php` → `framebuffers`, `config/rasterize.php` → `rasterize`, `config/images.php` → `images`, `config/drawing.php` → `drawing`, `config/embedded-displays.php` → `embedded-displays`, `config/fonts.php` → `fonts`; registers Windows, Bridge, Framebuffers, EmbeddedDisplays, Rasterize, Images, Fonts and Drawing providers; publishes the eight configs under tag `surface-config`.[^core]
* `BridgeServiceProvider`: singleton `toolkit-bridge` = `ToolkitManager`, alias `ToolkitManager::class`.[^bridge]
* `WindowsServiceProvider`: singleton `toolkit-windows` = `ToolkitWindowManager(toolkit-bridge, config('windows.menus'), config('windows.default_menu'))`, alias `ToolkitWindowManager::class`. Profiles parsed on first resolve.[^windows]
* `FramebuffersServiceProvider`: singleton `framebuffers` = `FramebufferManager`, alias `FramebufferManager::class`; `driver('native')` always, `driver('extended')` with ext-fb 0.10, `driver('auto')` (default) picks between them.[^framebuffers]
* `RasterizeServiceProvider`: singleton `rasterize` = `RasterizeManager`, alias `RasterizeManager::class`; `driver('native')` always, `driver('extended')` with ext-rasterize 0.10, `driver('auto')` (default) picks between them.[^rasterize]
* `ImagesServiceProvider`: singleton `images` = `ImagesManager`, alias `ImagesManager::class`; `driver('native')` always (PNG/JPEG need ext-gd), `driver('extended')` with ext-imgdec 0.10, `driver('auto')` (default) picks between them.[^images]
* `DrawingServiceProvider`: singleton `drawing` = `DrawingManager(config, framebuffers, rasterize)`, alias `DrawingManager::class`; `renderer('velvet', …)` built in.[^drawing]
* `EmbeddedDisplaysServiceProvider`: singleton `displays` = `EmbeddedDisplayManager(framebuffers, config('embedded-displays.defaults'), circuit catalog when bound, post to event-loop when bound)`, alias `EmbeddedDisplayManager::class`.[^embedded-displays]
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
[^images]: ImagesServiceProvider
[^embedded-displays]: EmbeddedDisplaysServiceProvider
[^drawing]: DrawingServiceProvider
