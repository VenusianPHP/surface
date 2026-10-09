---
type: Module
title: Drawing
description: "Rendering engines: one draw API over a framebuffer, frames recorded then drawn, DrawingManager, VelvetGE the software engine, GpuRenderingEngine over a package's device."
resource: src/Surface/Drawing/
tags: [surface, drawing, rendering, velvet]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-04T01:28:52Z }
sources:
  - id: contract
    resource: src/Surface/Contracts/Drawing/RenderingEngine.php
    title: RenderingEngine, DrawingException
  - id: base
    resource: src/Surface/Drawing/RenderingEngine.php
    title: "The engine base: frames, state, command lowering"
  - id: velvet
    resource: src/Surface/Drawing/Velvet/VelvetGE.php
    title: VelvetGE
  - id: gpu
    resource: src/Surface/Drawing/Gpu/
    title: GpuRenderingEngine, GpuDevice, DrawList, Lowering
  - id: manager
    resource: src/Surface/Drawing/DrawingManager.php
    title: DrawingManager
---

# Overview

Rendering engine = draws into a framebuffer. Nothing else: draining is the framebuffer's; hooks, frame clock, worker pool belong to whatever owns the frame (later, Core).[^contract]

```php
$fb = app('framebuffers')->driver('extended')->ring(FormatSpec::rgba8(), 320, 240, 3);
$velvet = app('drawing')->renderer('velvet', ['framebuffer' => $fb]);
$velvet->frame(fn (RenderingEngine $g) => $g->clear(Color::hex('#101820'))->push()->translate(160, 120)->rotate(0.3)->fillRect(-40, -20, 80, 40, Color::hex('#ff6600'))->pop());
$bytes = $fb->flush(FormatSpec::bgra8());
```

Layers, each usable alone: Framebuffers (pixels) · Rasterize (shapes → spans) · Drawing (engine over both).

# Frames

* Draw + state calls only between `begin()` / `end()`, or inside `frame(callable)`. Outside: `DrawingException`.
* Calls recorded in order, already transformed. `end()` draws the list (only what changed, below), keeps it. `replay()` draws it whole again.
* `frame()` whose callable throws: dropped, nothing drawn, last frame stays.
* Each frame starts identity transform, no clip.
* Image sources read when the frame is drawn: a frame can draw its own framebuffer.

# Partial frames

`end()` compares a frame that begins with `clear()` with the last frame, command by command, by position.

* Equal command (`==`) at the same place: unchanged. `image` never equal (source may hold new pixels).
* Changed: box of the new command + box of the one it replaced; extra commands either side. Boxes: geometry floor/ceil, +1 px for AA; strokes +2·stroke+1 (miter reaches four half-strokes); cut by clip.
* Boxes snapped to framebuffer `damageGranularity()`, merged until disjoint → `damage()`.
* Drawn: each region gets the frame with clips cut to it, `['clear', rgba, Region]`, spans trimmed. Disjoint → no pixel drawn twice.
* Identical frame: nothing drawn, `damage()` `[]` (no ring present, no panel send).
* Whole: first frame, after `invalidate()`, paged framebuffer. Frame without leading clear: all drawn, damage = its boxes.
* `keepsFrame(fb)`: `preservesContentsOnPresent()`, or a ring (every engine repairs a ring before drawing). False → whole drawn, diff still reported.
* `invalidate()`: framebuffer written outside the engine.
* Text spans run top to bottom: trimming halves to the first row.

| Bench (20 frames, Mac) | native before → after | C before → after | changed |
|---|---|---|---|
| 240×240 RGB565 clock | 111.6 → 32.4 ms | 0.97 → 1.02 ms | 8.7% |
| 240×480 page, 2400 chars + counter | 89.8 → 30.8 ms | 28.3 → 30.6 ms | 0.1% |

# State

`push` / `pop` (transform + clip) · `translate` · `scale` · `rotate` (+x → +y, clockwise on screen) · `transform(Affine)` · `clip(?Region)` in framebuffer pixels, held to the surface. Canvas order: last transform called is the first a point goes through. `clear()` ignores clip.

# Verbs

`clear` · `fillRect` · `strokeRect` · `line` · `polyline` · `fillTriangle` · `fillPolygon` · `fillPath` · `fillEllipse` · `strokeEllipse` · `image`. `Color` per call. Zero/negative size, radius, stroke, opacity: nothing. Non-finite, or past 2²⁴ once transformed: `DrawingException`.

Commands the base hands an engine's `execute()`:[^base]

| Command | From |
|---|---|
| `['clear', rgba]` | `clear` |
| `['path', contours, FillRule, rgba, clip]` | rects, triangles, polygons, paths; turned ellipses |
| `['polyline', points, stroke, closed, rgba, clip]` | lines, polylines, stroked rects; turned stroked ellipses |
| `['ellipse', cx, cy, rx, ry, rgba, clip]` | ellipse under translate + scale |
| `['ring', cx, cy, rx, ry, stroke, rgba, clip]` | stroked ellipse under translate + scale |
| `['image', Framebuffer, Affine, 1..255, Filter, clip]` | `image` |

Stroke width × `sqrt(|det|)`. Turned ellipse → polygon within 0.1 px (`π·sqrt(r / 0.2)` sides, 12..1024). Trig lives here, never in Rasterize.

# Text

`text(string, x, y, Color, GFXFont)`, `textBounds(string, GFXFont)`: bitmap faces, drawn under the transform and clip. Whole-pixel opaque text lowers to a `spans` command (no rasterising); the rest to one `path`. See [Fonts](fonts.md).

# VelvetGE

Software engine, `velvet`. `new VelvetGE(Framebuffer, RasterizeDriver, ?Edges)`. Command → Rasterize spans → `paintSpans()`; image → `paintImage()`; clear → `fill()`. Edges default: anti-aliased where format blends (RGB, grey), hard otherwise.[^velvet]

Framebuffer kind = mode:

| Kind | Frame |
|---|---|
| full, dirty, ePaper | drawn over what is there; dirty epoch left to the drainer |
| ring | `repair()`, draw, `present()` |
| paged | drawn into current page, only its rows rasterised; holder: `setPage(0)` + `frame()` + drain, then `setPage(n)` + `replay()` + drain. Pages = same frame on a full buffer, byte for byte |

Measured (Mac, 320×240 RGBA8, 100 shapes + 1 image): 6.8 ms both extended · 310 ms extended geometry / native bytes · 670 ms native geometry / extended bytes · 990 ms both native.

# GPU engines

`new GpuRenderingEngine(GpuDevice, int $width, int $height, ?Edges, ?OutputTarget)`. Engine package supplies the `GpuDevice`; everything neutral here.[^gpu] Creator in a package = one call: `$drawing->extend('metal', fn (array $args, DrawingManager $drawing) => GpuRenderingEngine::from(new MetalDevice, $args, $drawing))`. `from()` reads `output` alone or `width` + `height`, and `edges`; checks before touching the device; refuses `framebuffer` by name (engine owns its own, `framebuffer()` answers a `GLFramebuffer`, see [Framebuffers](framebuffers.md)).

`GpuDevice`: `name()` · `target(w, h, samples): GLFramebuffer` · `draw(DrawList)` · `surfaces()` (kinds it presents into, best first) · `handles()` (native handles a window needs to make the surface; `instance` for Vulkan) · `adopt(LentSurface)` · `present(LentSurface): bool` · `release()`. Made and used on one thread.

* Frame: commands → `Lowering::lower()` → `DrawList` → `draw()`, then `framebuffer()->drawn(damage())`. Frames, damage, partial frames = base class's: target keeps its pixels, so a partial frame is scissored regions.
* Edges: anti-aliased = 4 samples resolved; hard = 1. Default from the output's `pixelFormat()`: hard where it cannot blend.

| Output | Engine does |
|---|---|
| window (`WindowOutput`) | borrows the first kind in `device->surfaces()` the window lends; `adopt()`, then `target()`. Resize: next `begin()` re-makes target at new size, frame drawn whole. Nothing in common → `DrawingException` naming engines the window can host. `release()` reclaims |
| display (`EmbeddedDisplay`) | `bind(framebuffer())`; display's `present()` reads back what changed |
| none | offscreen: holder drains `framebuffer()` |

`DrawList`: `width`, `height`, `vertices` (float32 LE x,y pairs, target pixels, origin top-left; quad = six vertices; triangle lists only, Metal and SDL_GPU have no fans), `operations`:

| Operation | Meaning |
|---|---|
| `[CLEAR, rgba]` | whole target, no blend |
| `[SCISSOR, Region]` | until next |
| `[SOLID, first, rgba]` | quad, no blend |
| `[STENCIL_FILL, first, count, FillRule]` | triangles into stencil; non-zero counts front up, back down; even-odd inverts |
| `[COVER, first, rgba]` | quad where stencil ≠ 0, resets to 0 |
| `[ELLIPSE, first, cx, cy, rx, ry, rgba]` | quad; coverage per pixel |
| `[RING, first, cx, cy, rx, ry, stroke, rgba]` | quad; band r ± stroke / 2 |
| `[UPLOAD, texture, Framebuffer]` | source texture from `toRgba8()`; an `HdrImage` from `rgba16f()` on an HDR target |
| `[IMAGE, first, texture, Affine, opacity, Filter]` | quad over placed corners; Affine maps target pixel centre → source |
| `[RECTS, first, count, rgba]` | count / 6 quads, one a span |

Lowering: `clear` → `CLEAR`, or `SCISSOR` + `SOLID` for a region · `path` → `STENCIL_FILL` + `COVER` (contour of n points = n − 2 triangles from its first point, cover = box floor/ceil) · `polyline` → `Stroker::outline()` then as path, non-zero · `ellipse` / `ring` → one quad a pixel past the shape · `image` → `UPLOAD` once a frame a source, `IMAGE` with the inverse placement · `spans` → `RECTS`. `SCISSOR` only when the clip changes.

Output contracts (`Surface\Contracts\Drawing`): `OutputTarget` gains `pixelSize()` and `pixelFormat()` (RGBA8 for a window, wire format for a display) · `WindowOutput extends Pipeable`: `surfaces()`, `lend(kind, borrower)`, `lent()`, `reclaim()` · `SurfaceKind`: `METAL_LAYER`, `VULKAN_SURFACE`, `GL_CONTEXT`, `SDL_WINDOW`, `DMABUF`, each with `handle()` and `engines()` · `LentSurface`: kind, handles by name, live `size()`, `released()` · `SurfaceBorrower` (the engine): `framebuffer()`, `lendingHandles()`, `presentInto(LentSurface): bool`.

Staged frames. Optional device interfaces, engine uses each when device implements it; four packages' devices without them behave as before.

- `SwitchesVsync`: engine applies lent surface `vsync()` at adopt + every `onVsync()`; first of `VSync::fallbacks()` device lists (Mailbox/Adaptive/Off fall back to On). `vsync()` = applied mode, null offscreen / display / device keeps own. Device without On refused.
- `TargetsFormats`: args `format` (`TargetFormat`) + `colorspace` (`ColorSpace`, default format's first). Rgba8 sRGB stays `target()`. Pair must be in `TargetFormat::colorSpaces()`; displays refused (panels read RGBA8); checked before device touched. HDR target: `readRgba8()` sRGB-clamped, implements `HdrReadback`; SDR colours land at lent `hdr()->sdrWhiteLevel`. `hdrSnapshot()` → `HdrImage`.
- `QueuesFrames`: arg `frames_in_flight` 1-3, set after adopt, before target.
- `ReportsPresents`: `submitted()`, `presents()` (`PresentTiming`: frame, presentedAt ns on hrtime clock, refreshInterval), `waitPresented(frame, timeoutNs)`.
- Arg `resolution` [w, h], window output only: target fixed through resizes; resize marks whole target drawn. Device presents into `LentSurface::presentRect()` with lent filter, clears rest.
- `DamageHistory`: devices `record()` target `damage()` per present (surface px, via placement; scaled = +1px each side); `since(age)` = union of last age presents (EGL buffer age; VK per image index); unknown/too old/resize/move = whole surface. `flipped()` for GL bottom-left.

# Manager

`app('drawing')->renderer(?string $engine, array $args)`: new engine every call; null = `config('drawing.default')`. `extend(name, fn (array $args, DrawingManager): RenderingEngine)` for engine packages. Unknown name: `DrawingException` listing registered engines + the package for `metal`, `opengl`, `vulkan`, `sdl3`.[^manager]

Public for package creators: `edgesFrom(array): ?Edges` (`edges` as an `Edges`, `'hard'` or `'antialiased'`; null = engine picks) and `framebufferFrom(array, string $engine = 'velvet'): Framebuffer` (an output's own, one handed in, or one made from the sizing arguments).

Velvet args: `output` alone (an `OutputTarget`: Velvet draws over its `framebuffer()`, so a canvas pipes and a display sends what changed), `framebuffer` alone, or `width` + `height` with `mode` (`full`, `dirty`, `epaper`, `paged` + `page_rows`, `ring` + `frames` = 2), `format` (RGBA8), `framebuffers` (driver); `rasterize` (driver); `edges`. Unknown key throws.

[^contract]: `Surface\Contracts\Drawing`.
[^base]: `Surface\Drawing\RenderingEngine`: an engine supplies `name()`, `framebuffer()`, `execute()`.
[^velvet]: Byte-identical to Rasterize + framebuffer called directly; `VelvetTest` enforces through every driver pairing.
[^gpu]: `Surface\\Drawing\\Gpu`: `GpuRenderingEngine`, `GpuDevice`, `DrawList`, `Op`, `Lowering`.
[^manager]: Not a Voyager `Manager`: engines are built per call from arguments, never cached.
