---
type: Module
title: Framebuffers
description: Pixel storage behind a FormatSpec: five kinds over a pixel store, bytes in PHP (native) or C (extended, ext-fb); span painting.
resource: src/Surface/Framebuffers/
tags: [surface, framebuffers, pixels, epaper]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-03T23:06:29Z }
sources:
  - id: contracts
    resource: src/Surface/Contracts/Framebuffers/
    title: Framebuffer, PixelStore, the kind contracts, FormatSpec
  - id: kinds
    resource: src/Surface/Framebuffers/
    title: StoreFramebuffer, the five abstract kinds, Layout, PixelMapper
  - id: fixtures
    resource: tests/Framebuffers/fixtures/
    title: 43 golden fixtures
---

# Overview

Framebuffer = pixels in a host's own byte format. Needs no rendering engine: mint, draw, drain.[^contracts]

```php
$fb = app('framebuffers')->driver()->dirty(new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B16), 240, 135);
$fb->fill(0)->setSegment(20, 20, 100, 50, 0xF800);
foreach ($fb->damage() as $region) { $panel->send($region, $fb->flushRegion($region, $fb->hostFormat())); }
$fb->beginEpoch();
```

# Structure

Kind = logic over a `PixelStore` (the pixel glob). Kinds written once, abstract. Flavor = where the store comes from.[^kinds]

| Kind | Abstract | Adds |
|---|---|---|
| full | `FullFramebuffer` | nothing: one frame, keeps contents |
| dirty | `DirtyFramebuffer` | write record: `damage()`, `beginEpoch()`; bulk write = its bounding box; >16 rects collapse to one |
| ePaper | `ePaperFramebuffer` | mono, planar + palette, or packed codes + palette; starts as paper, `clear()` = paper; `channelDump(ink)` |
| paged | `PagedFramebuffer` | one `page_rows` window over a taller surface, any format; writes off-page drop; `pagesTouching(region)` |
| ring | `RingFramebuffer` | swap chain of n dirty frames |

| Flavor | Store | Bytes |
|---|---|---|
| `native` | `NativePixelStore`: PHP string + `Packing` | PHP; `pointer()` 0 |
| `extended` | `ExtendedPixelStore`: one `FbBuffer` | C (ext-fb 0.10); `pointer()` an address |

`Layout::of(spec)` = one rule set for storable specs; both flavors resolve there. `PixelMapper` = colour ↔ word in integers, same arithmetic as ext-fb. Result: identical bytes; fixtures + `ParityTest` enforce.[^fixtures]

# Ring

Front = last finished frame (readers). Back = frame being drawn. On the ring: draw calls → back, drain calls → front.

* `present()`: back → front; next back = unheld frame presented longest ago.
* `hold()` / `release()`: reader pins the front across loop turns; held frame never drawn into. 2 frames: drawer waits (`ready()` false, draw calls throw). 3+: never waits; frames the reader missed drop. Beyond 3: history, `frame(age)`.
* `age()`: buffer age as EGL_EXT_buffer_age (0 undefined, 1 = equals front, n = n−1 presents behind).
* `repair()`: copy from front what changed since the back's frame; then draw only new changes. Call before drawing.
* `damage(?since)`: regions a reader that last drained serial `since` must send; whole surface once the record (last n presents) no longer reaches.

# Spans

`paintSpans(string $spans, int $rgba8)` on every kind and store. Spans = `Spans` bytes (7 each: y, x, length uint16; coverage uint8), what Rasterize answers. Whole list checked first: empty or outside span throws, nothing written.

* Effective alpha `a = intdiv(alpha × coverage + 127, 255)`. 0 → nothing; 255 → the colour's word, as `setSegment()`.
* RGB and palette-less grey: source-over through the mapper, `intdiv(src × a + dst × (255 − a) + 127, 255)` a channel; alpha `intdiv(255 × a + dst_a × (255 − a) + 127, 255)`.
* Mono, palette, planar: the colour where `a ≥ 128`, nothing below.
* Dirty/ring: bounding box is damage; ring paints the back; paged drops off-page rows (checks against the whole surface first).

Integers throughout; `FbBuffer::paintSpans()` does the same in C. Fixtures 36–43 + spans parity enforce.

# Words

mono 0/1 (1 = white) · grey 0..max (2/4/8 bits, no palette) · packed index = palette code · planar = ink mask (0 = paper) · RGB packed at depth, `0xRRGGBBAA` at 32. `ChannelOrder` (RGB/BGR, RGBA/BGRA/ARGB/ABGR) changes bytes, never the word. `FormatSpec::rgba8()`, `bgra8()`.

# Rules

* Pixel outside surface throws `FramebufferException`; `setSegment()` clips.
* Pixel list with any bad entry refused whole.
* Sides 1..65535; palette ≤ 16 inks.
* `extended` detected by `class_exists(FbBuffer::class)`; a 0.8 ext named `fb` does not count.

[^contracts]: `Framebuffer` is the 0.8 pixel API, unchanged.
[^kinds]: Ten concrete classes, each a few lines: `Native*` / `Extended*`.
[^fixtures]: Every driver runs every fixture through `FixtureRunner`.
