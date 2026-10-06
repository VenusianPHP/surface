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
    resource: src/Surface/Contracts/Drawing/OutputTarget.php
    title: OutputTarget, shared with TKCanvas
  - id: display
    resource: src/Surface/EmbeddedDisplays/EmbeddedDisplay.php
    title: EmbeddedDisplay, what to send
  - id: direct
    resource: src/Surface/EmbeddedDisplays/DirectEDisplay.php
    title: DirectEDisplay, pixels piped from memory
  - id: manager
    resource: src/Surface/EmbeddedDisplays/EmbeddedDisplayManager.php
    title: EmbeddedDisplayManager, app('displays')
  - id: tests
    resource: tests/EmbeddedDisplays/
    title: Pest coverage over recording fake panels
---

# Overview

Second drawing output; `TKCanvas` first. Both `Surface\Contracts\Drawing\OutputTarget`: `boundFramebuffer()`, `framebuffer()`, `present()`.[^output] `EmbeddedDisplay` is not `Pipeable`; [DirectEDisplay](#directedisplay) is. Display owns one decision: what to send. No hook, clock or engine: sketch holds the engine.[^display]

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

# DirectEDisplay

`DirectEDisplay extends EmbeddedDisplay`, `Pipeable`: same regions, no PHP bytes.[^direct] Per region: `openWindow(x, y, w, h)` on the chip, then one `pixelBus()->writeFrom(spans)` read straight out of the framebuffer's ext-fb memory. Full-width region = one span (rows contiguous); else one span a row. ext-fb packs rows tightly: stride = width × bytes a pixel.

```php
$tft = app('displays')->panel('st7796', direct: true);
$engine = app('drawing')->renderer('velvet', ['output' => $tft]);
```

| Needs | Else |
|---|---|
| panel is gpio/contracts `PipeablePanel` | `notPipeable` at construction |
| `pixelBus()` not null (spidev; not I2C, offloaded, MPSSE) | `noMemoryBus` at construction |
| `pointer()` ≠ 0 (extended driver; `framebuffer()` mints on it, refuses another driver) | `cannotPipe` at `bind()` |
| host format = panel `formatSpec()`, top-down rows | `cannotPipe` |
| whole bytes a pixel: RGB565, RGB666, RGB888, RGBA8888, INDEX8 | `cannotPipe` (sub-byte, planar) |
| not paged | `cannotPipe` |
| panel format unchanged since bind | `formatChanged` at `present()`; `framebuffer()` re-mints |

Short `writeFrom()` (bytes ≠ asked, `-1` = kernel refused) → `pipeShort`, latched as a fault like any panel call. Chips: ST7735 / ST7789 / ST7796 (st77xx).

Measured, Pi 5 ST7796 480×320 RGB565 at 10 MHz, bufsiz 65536, same VelvetGE scenes:

| | `EmbeddedDisplay` | `DirectEDisplay` |
|---|---|---|
| whole frame (wire alone 246 ms) | 298 ms | 246.5 ms |
| clock, 30–35 KB changed | 29–33 ms | 28–32 ms |
| bouncing ball, partial | 68 fps | 82 fps |
| bouncing ball, whole | 3.3 fps | 4.0 fps |

# Lifecycle

* `show()` / `hide()`: `Switchable` only, else `notSwitchable`. Hidden: present sends nothing. Show: next present whole.
* Fault: panel call throws → latched, nothing more sent, one `DisplayFaulted(display, error)` mail (`display.faulted.<name>`) to `event-loop` when bound. Sketch exceptions not caught.
* `close()`: terminal, idempotent; `setDisplay(false)` where switchable and not faulted; leaves manager. Chip + bus stay sketch's.

# Manager

`app('displays')` = `EmbeddedDisplayManager(framebuffers, config defaults, catalog, post)`.[^manager]

* `attach(DisplayPanel, name, direct = false)`: `direct: true` builds a `DirectEDisplay`. Throws `nameTaken`, `notADisplayPanel` (no `formatSpec()`), `notBooted` (`BootSequence` not booted).
* `panel(panel, ?config, ?name, direct = false)`: `app('circuit')->conjure(panel, config)` (scrapyard-io/framework), name `panel` or `panel.config`; again → same display; again with `direct: true` over one attached otherwise → `nameTaken`. No catalog → `noCatalog`.
* `display`, `has`, `displays`, `detach` (closes), `destroy` (closes all, first failure rethrown after).

# GPU framebuffers

* `pixelSize()` (panel width, height) and `pixelFormat()` (wire format) on every display, as `OutputTarget` asks.
* A GPU engine's `GLFramebuffer` binds like any framebuffer and is sent as damage through `flushRegion()`, converted to the panel's format.
* On a `DirectEDisplay`: `bind()` gives it an ext-fb staging copy in the panel's format (`stageIn()`); `present()` runs `stage($region)`, then pipes the staging copy's memory. Refused with the reason when the panel's format is sub-byte, planar or bottom-up; refused framebuffer stays unstaged.

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

`tests/Fixtures/FakePanels.php`: `FakeWindowPanel`, `FakeWholePanel`, `FakeInkPanel`, `FakeWindowInkPanel`, `FakeUnbootedPanel`, `FakeFormatlessPanel`, `FakePipePanel` over a `FakeMemoryBus`; record `transmit` / `refresh` / `display` / `window` calls and the spans. `DirectEDisplayTest` reads the piped bytes back from `dump()` at each span's offset and holds them to `flushRegion()`. `EndToEndTest`: VelvetGE clock on a fake TFT, whole once then the time's box.[^tests]

[^contracts]: EmbeddedDisplay, EmbeddedDisplayException, Mail\DisplayFaulted
[^output]: OutputTarget, shared with TKCanvas
[^display]: EmbeddedDisplay, what to send
[^direct]: DirectEDisplay, pixels piped from memory
[^manager]: EmbeddedDisplayManager, app('displays')
[^tests]: Pest coverage over recording fake panels
