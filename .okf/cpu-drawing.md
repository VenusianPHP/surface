---
type: Architecture
title: CPU drawing — engines, canvases, Rasterizer
description: >-
  Sketch draws one Drawing2D hook into a host-format framebuffer it never
  touches. Five in-house engines, each a canvas plus one buffer kind.
tags: [surface, drawing, cpu, engines, rasterizer]
status: draft
generated: { by: cursor-grok-4.6/cursor, at: "2026-09-18T04:45:00Z" }
sources:
  - id: spec
    resource: docs/superpowers/specs/2026-09-17-cpu-rendering-design.md
    title: CPU rendering design
  - id: cpu-draw-target
    resource: src/Surface/Contracts/Drawing/CPUDrawTarget.php
    title: CPUDrawTarget
  - id: paged-draw-target
    resource: src/Surface/Contracts/Drawing/PagedDrawTarget.php
    title: PagedDrawTarget
  - id: cpu-host
    resource: src/Surface/Contracts/Drawing/CPUHost.php
    title: CPUHost
  - id: cpu-engine
    resource: src/Surface/Contracts/Drawing/CPUEngine.php
    title: CPUEngine
  - id: rasterizer
    resource: src/Surface/Drawing/Rasterizer.php
    title: Rasterizer
  - id: canvases
    resource: src/Surface/Drawing/Canvases
    title: CPU canvases
  - id: engines
    resource: src/Surface/Drawing/Engines
    title: Five CPU engines
  - id: manager
    resource: src/Surface/Drawing/CPUEngineManager.php
    title: CPUEngineManager
  - id: alias
    resource: src/Surface/Drawing/MagicAliases/CPU.php
    title: CPU MagicAlias
  - id: config
    resource: config/cpu.php
    title: cpu config
---

# Overview

Sketch draws with one hook on CPU or GPU. CPU path: engine owns a
framebuffer in the panel's own byte format. Sketch never touches it.[^spec]

```php
$canvas = CPU::driver('dirty')->attach(new CPUHost(128, 64, $ssd1306->formatSpec()));
$canvas->onDraw(fn (Drawing2D $g, Frame $f) => $g->fillCircle(64, 32, 20, Color::hex('#fff')));

$canvas->renderFrame();
foreach ($canvas->damage() as $region) {
    $ssd1306->transmit($region->x, $region->y, $canvas->flushRegion($region, as_array: true), $region->width, $region->height);
}

$gpu->drawing()->texture($canvas->rgba8(), 128, 64);   // same pixels onto any GPU engine
```

`CPU` MagicAlias (`cpu-engines`) names an engine. `CPUEngineManager`
resolves `cpu.<engine>` from `config/cpu.php` (`CPU_ENGINE` default
`dirty`). `DrawingServiceProvider` binds the five singletons; each
engine asks `FramebufferManager::driver()` at every attach, so a
`FRAMEBUFFER_DRIVER` change needs no reboot.[^manager][^alias][^config]
Same seam as GPU: injected config, no global helper, missing binding
is the container's own not-found.

GPU decisions stay in [drawing](/drawing.md). Bytes live in
[framebuffers](/framebuffers.md).

# Vocabulary

| Type | Role |
|---|---|
| `CPUDrawTarget` | `DrawTarget` plus `engine()`, `hostFormat()`, `flush` / `flushRegion` (null spec = host, zero transcode), `damage()`, `rgba8()`. No framebuffer getter. |
| `PagedDrawTarget` | `onPage(sink, as_array)` — replace, not stack. Fired per page inside `renderFrame()`. The paged engine's `attach()` returns this. |
| `CPUHost` | readonly attach value: `width`, `height`, `format`, `frames = 2` (nframes), `page_rows = 8` (paged). Engines read what they need. |
| `CPUEngine` | `DIRTY` / `FULL` / `EPAPER` / `PAGED` / `NFRAMES`. |
| `CPUEngineDriver` | `engine()`, `attach(CPUHost): CPUDrawTarget`. |
| `Rasterizer` | the one `Drawing2D` over any `Framebuffer` + `PixelMapper`. |
| `CPUCanvas` | abstract target: `SchedulesFrames` + Rasterizer + texture table. |
| `FullCanvas` / `DirtyCanvas` / `EPaperCanvas` / `PagedCanvas` / `NFramesCanvas` | one canvas per engine. Scale is always `1.0`. |

`CPUEngineBase` is stateless. `driver()` is `$this->framebuffers->driver()`
on every attach. Five engines name themselves and mint their canvas.
`PagedEngine::attach()` is covariant `PagedDrawTarget`. `NFramesEngine`
passes `$host->frames`; `PagedEngine` passes `$host->page_rows`. Manager
method names follow Voyager's studly map: `createEpaperDriver`,
`createNframesDriver`.[^engines]

# Decisions

- **Drawing2D, not Executor.** A software rasterizer eating Painter's
  triangle stream was rejected: 1-bit panels would get fans, quads, and
  alpha they cannot express. One `Rasterizer` implements `Drawing2D`
  with integer primitives. Hook signature identical on both paths.
  CPU pixels in a GPU window = `rgba8()` → `texture()`.[^spec]
- **Batches, never per-pixel.** Every primitive becomes `setSegment`
  (one call per axis-aligned rect, one span per polygon row) or
  `setPixels` (one call per hairline / image). On `native` that is one
  ext call, not N.
- **Two paths.** Identity / translate-only → integer fast path.
  Rotate / scale → points through the stack → scanline polygon fill.
  Circles use Painter's `segments()` on both. `Affine` and `Geometry`
  are shared with [the Painter](/drawing.md).
- **Colour-key, no blending.** Texel alpha `< 128` skipped, else opaque.
  CPU path draws opaque this slice. Alpha ignored at map time except B32.
- **Clear policy per engine.** Preserving engines (`dirty`, `full`,
  `epaper`) fill once at attach. Later `setClearColor()` is stored and
  has no visible effect — `Drawing2D::clear(Color)` takes its own
  colour. Clearing per frame on `dirty` would dirty everything.
  `nframes` and `paged` clear like the GPU. `epaper`'s default is
  **paper** (`EInkColor::WHITE`), set before `bootSchedule()` — a
  black attach would ink the panel.[^canvases]
- **Damage is an epoch.** `renderFrame()` opens one. `damage()`
  answers it until the next. Consumer: `renderFrame(); foreach damage
  → transmit(flushRegion)`. No explicit clearing.
- **True U8G2 paging + `onPage`.** One page of RAM. Hook runs
  `ceil(h / page_rows)` times per frame, same `Frame`, everything
  outside the current page clipped. Sketch hooks must be pure. Not
  "render once, emit per page" — that is `full` with paged output.
  Why it exists: Pico-class hosts and page-addressed panels (SSD1306).
- **Paged `flush()` is host-only.** `flush()` / `flushRegion()` /
  `rgba8()` re-run the page loop and concatenate. A second render,
  never a retained full frame. Foreign spec throws
  `DrawingException::pagedHostOnly()`. Transcode from a full canvas.
- **`SchedulesFrames` split.** Engine-neutral half of the frame loop
  (hook, clear colour, continuous/pending, `Frame` clock) lives in
  the trait. `RunsFrames` keeps only the GPU Painter / Executor
  frame. `GPUView` and `StagedWindow` keep their shape.
- **One canvas, two consumers.** A `CPUDrawTarget` can drive a CPU
  stage and a real panel in the same tick — `CPUStagedWindow` forwards
  `flush` / `damage` / `rgba8`. `Drawing2D::releaseTexture()` is on
  the contract: Painter flushes then the executor; Rasterizer drops
  the handle so a per-frame mint does not leak.

# Engines

| Engine | Canvas | Buffer minted | `begin()` | `present()` | `damage()` |
|---|---|---|---|---|---|
| `dirty` | `DirtyCanvas` | `dirty()` | `beginEpoch()`; no clear | — | regions written this frame, snapped |
| `full` | `FullCanvas` | `full()` | no clear | — | whole surface |
| `epaper` | `EPaperCanvas` | `epaper()` | no clear | — | whole surface |
| `paged` | `PagedCanvas` | `paged(page_rows)` | per page: `setPage`, fill clear colour, clip = page window, hook once per page | — | one region per page |
| `nframes` | `NFramesCanvas` | `ring(frames)` | fill back with clear colour | flip | whole surface |

A hook exception still `present()`s. Preserving buffers fill once at
attach.

# Not in this slice

- `Windowable::cpu()` — needs bytes-in twins on AppKit and GTK. Seam
  is `CPUDrawTarget` + `rgba8()`.
- EmbeddedPanels sink (push present into an IC). Pull model this
  slice, except `paged`'s `onPage`.
- Native rasterisation (`RastersNatively`): seam declared, no driver
  implements it.
- Blending on CPU, dithering. Text landed — see [fonts](/fonts.md).
- Canvas over GPU + CPU targets.

[^spec]: CPU rendering design
[^cpu-draw-target]: CPUDrawTarget
[^paged-draw-target]: PagedDrawTarget
[^cpu-host]: CPUHost
[^cpu-engine]: CPUEngine
[^rasterizer]: Rasterizer
[^canvases]: CPU canvases
[^engines]: Five CPU engines
[^manager]: CPUEngineManager
[^alias]: CPU MagicAlias
[^config]: cpu config
