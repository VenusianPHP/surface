---
type: Module
title: Drawing
description: "Rendering engines: one draw API over a framebuffer, frames recorded then drawn, DrawingManager, VelvetGE the software engine."
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
* Calls recorded in order, already transformed. `end()` draws the list, keeps it. `replay()` draws it again.
* `frame()` whose callable throws: dropped, nothing drawn, last frame stays.
* Each frame starts identity transform, no clip.
* Image sources read when the frame is drawn: a frame can draw its own framebuffer.

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

# VelvetGE

Software engine, `velvet`. `new VelvetGE(Framebuffer, RasterizeDriver, ?Edges)`. Command → Rasterize spans → `paintSpans()`; image → `paintImage()`; clear → `fill()`. Edges default: anti-aliased where format blends (RGB, grey), hard otherwise.[^velvet]

Framebuffer kind = mode:

| Kind | Frame |
|---|---|
| full, dirty, ePaper | drawn over what is there; dirty epoch left to the drainer |
| ring | `repair()`, draw, `present()` |
| paged | drawn into current page, only its rows rasterised; holder: `setPage(0)` + `frame()` + drain, then `setPage(n)` + `replay()` + drain. Pages = same frame on a full buffer, byte for byte |

Measured (Mac, 320×240 RGBA8, 100 shapes + 1 image): 6.8 ms both extended · 310 ms extended geometry / native bytes · 670 ms native geometry / extended bytes · 990 ms both native.

# Manager

`app('drawing')->renderer(?string $engine, array $args)`: new engine every call; null = `config('drawing.default')`. `extend(name, fn (array $args, DrawingManager): RenderingEngine)` for engine packages. Unknown name: `DrawingException` listing registered engines + the package for `metal`, `opengl`, `vulkan`, `sdl3`.[^manager]

Velvet args: `framebuffer` alone, or `width` + `height` with `mode` (`full`, `dirty`, `epaper`, `paged` + `page_rows`, `ring` + `frames` = 2), `format` (RGBA8), `framebuffers` (driver); `rasterize` (driver); `edges`. Unknown key throws.

[^contract]: `Surface\Contracts\Drawing`.
[^base]: `Surface\Drawing\RenderingEngine`: an engine supplies `name()`, `framebuffer()`, `execute()`.
[^velvet]: Byte-identical to Rasterize + framebuffer called directly; `VelvetTest` enforces through every driver pairing.
[^manager]: Not a Voyager `Manager`: engines are built per call from arguments, never cached.
