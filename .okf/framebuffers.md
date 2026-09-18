---
type: Architecture
title: Framebuffers — host-format store, php and native
description: >-
  Store is the host format. Two drivers (php, native) mint five buffer
  kinds against the same golden fixtures. Sketch never holds the bytes.
tags: [surface, framebuffers, packing, pixel-mapper, ext-fb]
status: draft
generated: { by: cursor-grok-4.6/cursor, at: "2026-09-18T01:20:00Z" }
sources:
  - id: spec
    resource: docs/superpowers/specs/2026-09-17-cpu-rendering-design.md
    title: CPU rendering design
  - id: format-spec
    resource: src/Surface/Contracts/Framebuffers/FormatSpec.php
    title: FormatSpec family
  - id: region
    resource: src/Surface/Contracts/Framebuffers/Region.php
    title: Region
  - id: eink
    resource: src/Surface/Contracts/Framebuffers/EInkColor.php
    title: EInkColor
  - id: driver-contract
    resource: src/Surface/Contracts/Framebuffers/FramebufferDriver.php
    title: FramebufferDriver
  - id: rasters
    resource: src/Surface/Contracts/Framebuffers/RastersNatively.php
    title: RastersNatively
  - id: mapper
    resource: src/Surface/Framebuffers/PixelMapper.php
    title: PixelMapper
  - id: packings
    resource: src/Surface/Framebuffers/Packings
    title: Nine packings
  - id: php-driver
    resource: src/Surface/Framebuffers/Php/PhpFramebufferDriver.php
    title: PhpFramebufferDriver
  - id: manager
    resource: src/Surface/Framebuffers/FramebufferManager.php
    title: FramebufferManager
  - id: config
    resource: config/framebuffers.php
    title: framebuffers config
  - id: fixtures
    resource: tests/Framebuffers/fixtures
    title: Golden fixtures
---

# Overview

Store **is** the host format. `flush(null)` is a byte copy. Any other
spec transcodes through RGBA8: packing `toRgba8` then `fromRgba8`.
Always correct, never fast.[^spec]

Sketch talks the `Framebuffer` contract. Driver owns the store.
Engines never `new` a buffer — they call
`Framebuffers::driver()->dirty($host->format, $w, $h)` (or `full` /
`epaper` / `paged` / `ring`). Swap the driver in config, the five
[CPU engines](/cpu-drawing.md) run in C, nothing else moves.[^manager]

# Vocabulary

| Type | Role |
|---|---|
| `FormatSpec` | pixel format, bit depth, optional bit order / endianness / page axis / scan / palette / inverted. `equals()` is every field, palette by value. |
| `BitDepth` | includes `B2` and `B4` (packed-index ePaper, grey OLEDs). |
| `ChannelSpec` | `?int $code` — panel wire code; null = palette position. |
| `ChannelPalette` | `codes()`, `indexOf()`, `equals()`. |
| `Region` | readonly `(x, y, w, h)`. `wholeSurface`, `isEmpty`, `right` / `bottom`, `contains`, `intersect`, `union`, `touches` (overlap or edge/corner — the damage merge rule), `snap(DamageGranularity)` outward, clamped. |
| `EInkColor` | `WHITE=0 BLACK=1 RED=2 YELLOW=3 BLUE=4 GREEN=5 ORANGE=6`. `WHITE` is paper. Planar palettes list ink only; packed palettes list every code, white included. |
| `Framebuffer` | get/set/pixels/region/segment/clear/fill/blit/dump/flush/`flushRegion`/`toRgba8`/`pointer()` (`0` when PHP owns the bytes). |
| `DamageTrackingFramebuffer` | `beginEpoch()`, `damage()` snapped. The dirty buffer. |
| `PagedFramebuffer` | `pageRows`, `pages`, `setPage`, `page`, `pageRegion`. One window. |
| `MultiFrameFramebuffer` | `frames()`, `present()` (flip), `front()` read-only view of the presented frame. |
| `FramebufferDriver` | mints `full` / `dirty` / `epaper` / `paged` / `ring`. `driver()` is `'php'` or `'native'`. |
| `RastersNatively` | optional: `fillRect`, `line`, `blitRgba8`. Rasterizer probes once at construction. No driver implements it this slice. |

# Pixel words

Same `int` on both drivers:

- mono `0` / `1` (`1` = lit / white)
- `ROW_MAJOR` colour = packed word of that depth (B12 `0xRGB`, B16
  RGB565, B18 RGB888 with each channel's low two bits zero, B24
  `0xRRGGBB`, B32 `0xRRGGBBAA`)
- `ROW_MAJOR` B2/B4/B8 with a palette = the panel wire **code**
- B8 without palette = grey `0..255`
- `PLANAR` = channel **mask** (`1 << k` for palette channel `k`,
  `0` = paper)

Padding bits and bytes stay zero. A buffer starts as `fill(0)` — an
inverted plane's real pixels start at 1. Store is always top-down.
`ScanDirection::BOTTOM_TO_TOP` applies on output only (`flush` /
`flushRegion`), by the **output** spec. `toRgba8()` is always
top-down.

Defaults when a `FormatSpec` field is null: `MONO_HORIZONTAL` and
`PLANAR` bit order `MSB_FIRST`; `MONO_VERTICAL_PAGE` bit order
`LSB_FIRST`, page axis `VERTICAL`; packed index bit order
`MSB_FIRST`; B16 endianness `MSB`.

# Packings

`Packing::for(FormatSpec)` picks one class. Every packing:
`bytesFor`, `get`, `set`, `span` (fast horizontal run), `fill`,
`region` (sub-rect, pre-snapped), `granularity` (vertical page →
`rows(8)`; else `pixel()`), `toRgba8` / `fromRgba8`. `ext-fb`
implements this same table in C.[^packings]

| Class | Covers | Layout |
|---|---|---|
| `MonoHorizontalPacking` | `MONO_HORIZONTAL` B1 | row-major bits, rows byte-padded, `BitOrder` picks MSB/LSB within a byte |
| `MonoVerticalPagePacking` | `MONO_VERTICAL_PAGE` B1 | SSD1306: byte per column per 8-row page, `LSB_FIRST` = bit 0 is top row; `PageAxis::HORIZONTAL` spans 8 columns instead |
| `PackedIndexPacking` | `ROW_MAJOR` B2/B4/B8 | palette codes, `BitOrder` picks nibble/crumb order, B8 one byte |
| `Rgb444Packing` | `ROW_MAJOR` B12 | two pixels per three bytes, odd tail padded (ST77xx) |
| `Rgb565Packing` | `ROW_MAJOR` B16 | `Endianness` picks byte order |
| `Rgb666Packing` | `ROW_MAJOR` B18 | three bytes, six bits left-aligned (ST77xx wire) |
| `Rgb888Packing` | `ROW_MAJOR` B24 | |
| `Rgba8888Packing` | `ROW_MAJOR` B32 | the `rgba8()` canonical; windows use this |
| `PlanarPacking` | `PLANAR` B1 + palette | N `MonoHorizontal` planes in palette order; plane k bit = (pixel is channel k) xor `inverted` |

# PixelMapper

`PixelMapper::for(FormatSpec)`. `map(Color): int`, `unmap(int): Color`.
Driver-independent — colour policy in PHP, bytes wherever the driver
puts them.[^mapper]

- Mono: luma ≥ 0.5 → 1 (`0.2126 R + 0.7152 G + 0.0722 B`).
- Colour depths: quantise channels (B12 4-bit, B16 5/6/5, B18 low two
  bits zero, B24 8-bit, B32 8-bit plus alpha).
- Indexed / planar: nearest `EInkColor` by RGB distance over
  `{WHITE} ∪ palette`.
- Planar `unmap` of a multi-bit mask is the **lowest set bit**'s
  colour. `0` is paper. An exact-word miss used to return white and
  broke php/native parity on PLANAR.
- Alpha ignored except at B32.

# Drivers

| Driver | Package | Bytes |
|---|---|---|
| `php` (default) | `surface/framebuffers`, in-house | PHP packed strings; fake-provable; always available |
| `native` | `php-io-extensions/fb` (`ext-fb`) + `jovian/fb` | C buffers behind handles |

`FRAMEBUFFER_DRIVER` (`config/framebuffers.php`, default `php`) picks
the manager's default. `native` with `jovian/fb` absent → container
not-found, same as a missing GPU package. `jovian/fb` present but
`ext-fb` not loaded → `FramebufferException::extensionMissing('fb')`
from the driver's constructor. Surface never imports `Jovian\`.
`jovian/fb` is `suggest`, never `require`.[^config]

**No-bleedover.** Golden fixtures authored first, from datasheets and
hand-packed bytes, before either driver. php (Tasks 3–4) and ext-fb
(Tasks 5–6) were separate agents that read contracts, the packings
table, and the fixtures — never each other's code. Gate: each passes
the fixtures alone; then a property suite drives both with the same
random op sequences and asserts equal `flush()`, `damage()`,
`rgba8()`. A disagreement is a fixture question first, a bug second.

**Fixtures are the contract.** `tests/Framebuffers/fixtures/` — 27
files, hand-packed, never generated by a driver. `FixtureRunner`
drives any `FramebufferDriver` through those files.[^fixtures]

`php` driver mints all five kinds:

| Framebuffer | Contract | Adds | Preserves |
|---|---|---|---|
| `FullFramebuffer` | `Framebuffer` | nothing | true |
| `DirtyFramebuffer` | `DamageTrackingFramebuffer` | write unions a `Region`; merge when touching; over 16 rects → bbox; `damage()` snapped; `beginEpoch()` clears | true |
| `EPaperFramebuffer` | `Framebuffer` | accepts PLANAR + palette, `MONO_HORIZONTAL` B1, `ROW_MAJOR` B2/B4/B8 + palette; `channelDump(EInkColor)` | true |
| `PagedFramebuffer` | `PagedFramebuffer` | one `page_rows`-tall window; writes outside dropped; `flush` / `region` = current page only | false |
| `RingFramebuffer` | `MultiFrameFramebuffer` | `frames ≥ 2`; writes to back; `present()` advances; reads from front | false |

# Refusals

- ePaper refuses anything that is not PLANAR B1 + palette,
  `MONO_HORIZONTAL` B1, or `ROW_MAJOR` B2/B4/B8 + palette.
- A write outside a php grid throws `outOfRange`. Rasterizer clips
  first. The ext never throws — out-of-range writes drop, reads
  answer 0, a refused `create` answers 0. `jovian/fb` raises Surface
  exceptions before calling.
- Paged `page_rows` that cannot tile throws `pageRows()`.
- `RastersNatively` is declared, not implemented. Do not invent a
  second raster path.
- Surface production code does not import `Jovian\` or
  `Surface\Framebuffers` from `jovian/fb` (contracts only).
- Drivers do not read each other. A fixture failure is a layout-math
  bug until proven otherwise.

[^spec]: CPU rendering design
[^format-spec]: FormatSpec family
[^region]: Region
[^eink]: EInkColor
[^driver-contract]: FramebufferDriver
[^rasters]: RastersNatively
[^mapper]: PixelMapper
[^packings]: Nine packings
[^php-driver]: PhpFramebufferDriver
[^manager]: FramebufferManager
[^config]: framebuffers config
[^fixtures]: Golden fixtures
