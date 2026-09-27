---
type: Architecture
title: Embedded displays
description: >-
  A CPU canvas presented on an IC display panel: what is sent and when, the
  manager, the displays dock resource, faults, and the one place Surface
  imports GPIO contracts.
tags: [surface, embedded-displays, gpio, panels, io-pools]
status: draft
generated: { by: claude-fable-5-1/claude-code, at: "2026-09-18T23:00:00Z" }
sources:
  - id: display
    resource: src/Surface/EmbeddedDisplays/EmbeddedDisplay.php
    title: EmbeddedDisplay
  - id: manager
    resource: src/Surface/EmbeddedDisplays/EmbeddedDisplayManager.php
    title: EmbeddedDisplayManager
  - id: resource
    resource: src/Surface/EmbeddedDisplays/EmbeddedDisplayResourceDriver.php
    title: EmbeddedDisplayResourceDriver
  - id: contract
    resource: src/Surface/Contracts/EmbeddedDisplays/EmbeddedDisplay.php
    title: EmbeddedDisplay contract
  - id: config
    resource: config/embedded-displays.php
    title: embedded-displays.php
  - id: tests
    resource: tests/EmbeddedDisplays/EmbeddedDisplayTest.php
    title: what is sent, byte for byte
  - id: spec
    resource: docs/superpowers/specs/2026-09-18-embedded-displays-and-canvas-design.md
    title: Embedded displays and Canvas design
---

# Shape

```
ssd1306, st77xx, ...   dept-of-scrapyard-robotics      implement GeneralPurposeIO\Contracts\IntegratedCircuits\DisplayPanel
        |                                               (+ WindowAddressable / RefreshesOnCommand / Switchable)
EmbeddedDisplay::panel('st7789')  ->  EmbeddedDisplay = panel + a canvas of finished pixels  ->  `displays` dock resource
```

`EmbeddedDisplay` is a `CPUDrawTarget`: same `onDraw` hook as every other
target. `renderFrame()` runs the canvas, then `present()` sends.[^display]

`attach()` asks for the intersection `DisplayPanel&FormatSpecification`:
`gpio/contracts` says nothing about pixel packing, so Surface is the side
that demands it.

# Two ways in

| Call | Who builds the panel |
|---|---|
| `EmbeddedDisplay::panel('st7789')` | the IC catalog, from `config/circuits/<slug>.php` |
| `EmbeddedDisplay::attach($panel, 'oled')` | the sketch |

`display('oled')` is a lookup of what is already attached, nothing more.

`panel()` is the one a sketch wants: slug in, wired panel out, no SPI and no
pin numbers in sketch code. It is idempotent — the name already taken hands
back the display that has it. The alias is `EmbeddedDisplay`, singular, same
as the class it fronts.

The catalog is resolved by container key `circuit`, the way `io-pool` and
`cpu-engines` are — not by `gpio/contracts`' registry contract, which is on
its own release cadence. What Surface checks is the panel that comes back;
anything that is not a `DisplayPanel&FormatSpecification` is refused by
name. Without a catalog bound, `panel()` says so rather than failing to
resolve.

Nothing is built at boot. A panel is conjured the first time a sketch asks
for it, because reaching a bus is a thing a program does on purpose.

# A panel is not a program

Nothing on this path starts a `LiveApplication`, an NSApplication or any
window. A panel sketch conjures, draws and pumps `IOPool`. The OS bridge is
built by `LiveApplication`, not by the service provider, precisely so this
stays true.

# What is sent

| Panel | Sent per frame |
|---|---|
| `WindowAddressable` | `damage()` regions, `flushRegion()` each |
| base `DisplayPanel` only | whole `flush()`, only when damage is non-empty |
| any, first present and after `show()` | whole frame — panel RAM is not the canvas until told |
| `paged` canvas | pages stream from inside the canvas frame through `onPage`; needs `WindowAddressable` |
| `RefreshesOnCommand` | the above, then `refresh($mode)` once, only if something was sent |

Bytes go out `as_array`, exactly the `transmit()` shape. Damage on a
page-packed format is full-width row bands (`DamageGranularity::rows`), so
an SSD1306 gets 128-byte page bands, never column windows.[^tests]

# Rules

- **One GPIO import site.** `GeneralPurposeIO\` appears only under
  `src/Surface/EmbeddedDisplays`. The contract in `Surface\Contracts` is
  GPIO-free so `venusian-surface/canvas` and window-only apps never load it.
  `panel()` and `refreshMode()` live on the concrete class.[^contract]
- **No concrete engine named.** The manager asks `cpu-engines` by name.
  Default by panel kind: `refreshing` → `epaper`, `addressable` → `dirty`,
  `whole` → `full` (`config/embedded-displays.php`).[^manager][^config]
- **Which canvas is swappable.** The display is `DrawsWith`: `drawWith()`
  takes any `CPUDrawTarget` of the panel's size and format, else
  `rendererMismatch`. A paged canvas re-wires `onPage`; the next present is
  whole. That is how `Canvas::engine()` puts a GPU engine on a panel — see
  [canvas.md](/canvas.md).
- **Host must be the panel.** Own `CPUHost` allowed (page rows, frames) but
  size and format must equal the panel's, else `hostMismatch`.
- **Boot first.** A panel that is a `BootSequence` and has not booted is
  refused (`notBooted`).
- **Fault latch.** A panel write that throws: fault stored, one
  `display.faulted.<name>` mail, nothing sent again. Recover with
  `detach()` + `attach()`. A sketch hook that throws propagates — not a fault.
- **Hidden = skipped.** `hide()` is `Switchable::setDisplay(false)`; frames
  skip; `show()` re-sends the whole frame. Non-switchable panel:
  `notSwitchable`.
- **close() is terminal, chip is not ours.** Output off where switchable,
  display leaves the manager. Bus and pins stay the sketch's to close.
- **Tick cost.** `displays` resource: one `renderFrame()` per display per
  tick. Bus time is spent inside the tick; nothing to draw costs
  nothing.[^resource]
- Teardown order in `LiveApplication::destroy()`: inputs, displays, stages,
  windows, bridge.

# Not here

Mirroring one canvas to a stage and a panel through this class (use
`Stage::emulate()` beside it), chunked transmits across ticks, a
`Maintained` cadence for Sharp memory LCDs. GPU rasterisation of a panel is
not here either — it is `Canvas::engine()`, which hands this class an
ordinary `CPUDrawTarget` that happens to be a `GPUCanvas`.

[^display]: EmbeddedDisplay
[^manager]: EmbeddedDisplayManager
[^resource]: EmbeddedDisplayResourceDriver
[^contract]: EmbeddedDisplay contract
[^config]: embedded-displays.php
[^tests]: what is sent, byte for byte
[^spec]: Embedded displays and Canvas design
