---
type: Module
title: EmbeddedDisplays
description: "An IC display panel as a drawing output beside TKCanvas: framebuffer in the panel's format, present() sends what changed."
resource: src/Surface/EmbeddedDisplays/
tags: [surface, embedded-displays, panels, output]
status: draft
generated: { by: claude-opus/5.5, at: "2026-10-04T00:00:00Z" }
sources:
  - id: contracts
    resource: src/Surface/Contracts/EmbeddedDisplays/
    title: EmbeddedDisplay, EmbeddedDisplayException, Mail\DisplayFaulted
  - id: output
    resource: src/Surface/Contracts/Drawing/Output.php
    title: Output, shared with TKCanvas
  - id: display
    resource: src/Surface/EmbeddedDisplays/EmbeddedDisplay.php
    title: EmbeddedDisplay, what to send
  - id: manager
    resource: src/Surface/EmbeddedDisplays/EmbeddedDisplayManager.php
    title: EmbeddedDisplayManager, app('displays')
  - id: tests
    resource: tests/EmbeddedDisplays/
    title: Pest coverage over recording fake panels
---

# Overview

Second drawing output; `TKCanvas` first. Both `Surface\Contracts\Drawing\Output`: `boundFramebuffer()`, `present()`.[^output] Display owns one decision: what to send. No hook, clock or engine: sketch holds the engine.[^display]

```php
$display = app('displays')->panel('ssd1306');
$engine = app('drawing')->renderer('velvet', ['framebuffer' => $display->framebuffer()]);
$engine->frame($draw);
$display->present();
```

Contract GPIO-free; class takes `GeneralPurposeIO\Contracts\IntegratedCircuits\DisplayPanel` + `formatSpec(): FormatSpec` by convention.[^contracts]

# Framebuffer

* `framebuffer(?kind, ?page_rows, frames = 2, ?driver)`: panel size, panel `formatSpec()`, bound. Same object while kind, size, format hold; chip changes format (rotation, colour mode) → next call mints new.
* Null kind: `RefreshesOnCommand` → `epaper`; `WindowAddressable` → `dirty`; else `full`. Override: `embedded-displays.defaults.{refreshing, addressable, whole}`.
* `paged`: needs `page_rows`, a `WindowAddressable` panel, rows a multiple of the panel's row unit (8 on vertical-page).
* `bind(Framebuffer)`: any format, panel size. Bytes converted at send.

# present()

| Situation | Sent |
|---|---|
| First, after `show()`, after `bind()` / new framebuffer | whole frame (`flush(panelFormat, true)`) |
| `WindowAddressable` + `DamageTrackingFramebuffer` | each damage region, `beginEpoch()` after |
| `WindowAddressable` + ring | `damage(last serial sent)`; same serial → nothing |
| damage `[]` | nothing, no refresh |
| not `WindowAddressable`, any damage | whole frame |
| paged | current page's rows; refresh after the last page |

Regions snapped to panel unit (vertical page: 8 rows full width; under 8 bpp: `8 / bpp` px across; else 1 px), then to framebuffer `damageGranularity()`, overlaps merged. One `refresh(refreshMode)` after sends on `RefreshesOnCommand`. Bytes as `list<int>`: `DisplayPanel::transmit()` takes that.[^display]

# Lifecycle

* `show()` / `hide()`: `Switchable` only, else `notSwitchable`. Hidden: present sends nothing. Show: next present whole.
* Fault: panel call throws → latched, nothing more sent, one `DisplayFaulted(display, error)` mail (`display.faulted.<name>`) to `event-loop` when bound. Sketch exceptions not caught.
* `close()`: terminal, idempotent; `setDisplay(false)` where switchable and not faulted; leaves manager. Chip + bus stay sketch's.

# Manager

`app('displays')` = `EmbeddedDisplayManager(framebuffers, config defaults, catalog, post)`.[^manager]

* `attach(DisplayPanel, name)`: throws `nameTaken`, `notADisplayPanel` (no `formatSpec()`), `notBooted` (`BootSequence` not booted).
* `panel(panel, ?config, ?name)`: `app('circuit')->conjure(panel, config)` (scrapyard-io/framework), name `panel` or `panel.config`; again → same display. No catalog → `noCatalog`.
* `display`, `has`, `displays`, `detach` (closes), `destroy` (closes all, first failure rethrown after).

# Panels (0.10 chips)

| Chip | Format | Region writes | Refresh | Switch | Kind |
|---|---|---|---|---|---|
| ssd1306 | 1-bit vertical pages | 8-row pages | none | yes | dirty |
| st7789 / st7796 | 12/16/18-bit row-major | yes | none | yes | dirty |
| ssd1680 BW | 1-bit horizontal | byte columns | full, partial | no | epaper |
| ssd1680 BWR | two 1-bit planes | byte columns | full | no | epaper |
| jd79661 | 2-bit, 4 inks | whole frame | full | no | epaper |
| spectra6 | 4-bit, 6 inks | whole frame | full | no | epaper |

# Tests

`tests/Fixtures/FakePanels.php`: `FakeWindowPanel`, `FakeWholePanel`, `FakeInkPanel`, `FakeWindowInkPanel`, `FakeUnbootedPanel`, `FakeFormatlessPanel`; record `transmit` / `refresh` / `display` calls. `EndToEndTest`: VelvetGE clock on a fake TFT, whole once then the time's box.[^tests]

[^contracts]: EmbeddedDisplay, EmbeddedDisplayException, Mail\DisplayFaulted
[^output]: Output, shared with TKCanvas
[^display]: EmbeddedDisplay, what to send
[^manager]: EmbeddedDisplayManager, app('displays')
[^tests]: Pest coverage over recording fake panels
