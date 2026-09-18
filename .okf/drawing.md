---
type: Architecture
title: GPU drawing — contracts, Painter, GPUView, CPU canvases
description: >-
  How a sketch draws into a native window with a GPU or a CPU framebuffer:
  engine-free contracts, one Painter over any Executor, one Rasterizer over
  any Framebuffer, GPUView, and the five CPU canvases.
tags: [surface, drawing, gpu, contracts, views]
status: draft
generated: { by: cursor-grok-4.6/cursor, at: "2026-09-18T06:20:00Z" }
sources:
  - id: contracts
    resource: src/Surface/Contracts/Drawing
    title: Drawing contracts
  - id: painter
    resource: src/Surface/Drawing/Painter.php
    title: Painter
  - id: rasterizer
    resource: src/Surface/Drawing/Rasterizer.php
    title: Rasterizer
  - id: schedules
    resource: src/Surface/Drawing/Concerns/SchedulesFrames.php
    title: SchedulesFrames
  - id: canvases
    resource: src/Surface/Drawing/Canvases
    title: CPU canvases
  - id: affine
    resource: src/Surface/Drawing/Affine.php
    title: Affine
  - id: geometry
    resource: src/Surface/Drawing/Geometry.php
    title: Geometry
  - id: gpuview
    resource: src/Surface/NativeWindows/Views/GPUView.php
    title: GPUView
  - id: spec
    resource: docs/superpowers/specs/2026-09-13-gpu-drawing-design.md
    title: Slice 1 design
  - id: slice2
    resource: docs/superpowers/specs/2026-09-13-gpu-drawing-slice2-opengl-design.md
    title: Slice 2 design
  - id: slice3
    resource: docs/superpowers/specs/2026-09-13-gpu-drawing-slice3-vulkan-design.md
    title: Slice 3 design
  - id: cpu-drawing
    resource: .okf/cpu-drawing.md
    title: CPU drawing
---

# Overview

`$window->gpu('scene', 'metal', x, y, w, h)->onDraw(fn (Drawing2D $g, Frame $f) => ...)`.
Surface names the engine, resolves it through `GPUEngineManager`
(`gpu-engines`, alias `gpu.<engine>` from `config/gpu.php`), and the window
engine mints the host through `mintGPU()`. Everything Surface-side is
fake-provable; the engine lives in `jovian/venusian-<engine>`.[^contracts]

`DrawTarget` is engine-free (hook, clear, continuous, redraw, size,
`renderFrame`). `GPUDrawTarget` adds `engine()` / `executor()` —
`OSGPUView` and `GPUStagedWindow` extend it. The `StagedWindow`
contract itself is engine-free so a CPU stage can share the window
verbs. `CPUDrawTarget` / `PagedDrawTarget` / `CPUHost` / the five
`CPUEngine` cases (`dirty` / `full` / `epaper` / `paged` / `nframes`)
are on the contracts. `Drawing2D::releaseTexture()` is the pair to
`texture()`: the Painter flushes then hands the handle to the
executor; the Rasterizer forgets it and `image()` throws
`DrawingException` once it is gone.

`Rasterizer` is the one `Drawing2D` over any `Framebuffer`. It takes a
`Framebuffer` and a `PixelMapper`, emits `setSegment` (one call per
axis-aligned rect, one span per polygon row) and `setPixels` (one call
per hairline / image), never one call per pixel. Identity and
translate-only transforms take the fast path; rotate and scale go
through a scanline polygon fill. Colour is opaque; texel alpha `< 128`
is skipped. `begin(?Region $page)` resets the affine stack and user
clip; a page clip survives `unclip()`. `RastersNatively` is an optional
seam probed once at construction — no driver implements it this slice.

`Surface\Drawing\Text` is engine-free layout over any `GFXFont`:
`Typesetter` places glyphs from the line-box top (`y` is the top, no
baseline shim), decodes coverage / inclusive runs, and caches by face
class. `GlyphAtlas` shelf-packs white RGB + coverage alpha with a
one-texel gutter. `surface/drawing` still does not import
`Surface\Fonts\`. `Drawing2D::text()` / `textBounds()` sit on both
drawers: the Painter emits one `TRIANGLES` batch per string (six
tinted vertices per placed glyph, UVs from a per-face-class atlas
held until `releaseAtlases()`); the Rasterizer writes one span per
glyph run (`fillRegion` on a translation, `scanlines()` otherwise).
Atlas bake starts at `min(512, max_texture_size)`.

`Affine` (immutable `a b c d tx ty`) and `Geometry` (segment counts,
ellipse rings, stroke quads) are shared with `Painter`. Compose is
**this × m** (right operand applies first). Singular invert is `null`.

`SchedulesFrames` is the engine-neutral half of a frame loop: hook,
clear colour, continuous vs on-demand, `Frame` clock. `bootSchedule()`
sets opaque black only when the class has not already set `$clear_color`
(the door `EPaperCanvas` uses). `RunsFrames` uses it and adds the
Painter / Executor frame. `CPUCanvas` uses it and adds a Rasterizer
over one framebuffer; `FullCanvas` and `DirtyCanvas` fill the
begin/present/damage pair. `EPaperCanvas` sets paper before
`bootSchedule()` so attach does not ink the panel; damage is the
whole surface. `PagedCanvas` is true U8G2: one page of RAM, the
same hook per page, `onPage` streams each page, `flush()` /
`rgba8()` re-run and concatenate, a foreign spec throws
`pagedHostOnly()`. `NFramesCanvas` clears the back, flips on
present, reads the front. Scale on a CPU canvas is always `1.0`.
Preserving buffers fill once at attach. A hook exception still
`present()`s. `flush()` / `flushRegion()` / `rgba8()` read the
framebuffer; a foreign spec transcodes except on a paged canvas.[^canvases]

# Vocabulary

`Executor` is the intersection every engine executes: clear, viewport,
scissor, vertex/index bytes, five topologies (no fans), instanced draws, one
RGBA8 texture, a per-draw `Transform`, mid-frame `readPixels()`, present.
Vertices are `x y z r g b a u v` (`pack('g9')`), indices `uint16`. Depth,
blending and a shared shader language are NOT on the contract;
`ExecutorCapabilities` says what an engine has so the Painter degrades
honestly (blending false → opaque).

# Decisions

- **Transform is projection only.** The Painter applies the sketch's affine
  stack per vertex on the CPU and emits target pixels; every draw in a frame
  shares one orthographic pixel→clip matrix (y up, +1 at top). Engines own
  any handedness flip. Batching survives translate/rotate.[^painter]
- **`clip()` is an untransformed axis-aligned scissor** in slice 1.
  Stroke widths are pre-transform pixels, so a scaled stroke scales with
  the shape. Hairline `line(..., stroke <= 1)` stays `LINES` and does not
  thicken.
- **`readPixels()` is mid-frame**: legal between `beginFrame()` and
  `endFrame()`, throws `DrawingException` outside. Every engine reads before
  present, so this is the common semantic, not a Metal special case.
- **`Frame` is a contract value**, not a component class.
- **Release before destroy**: `GPUView::remove()` calls `executor->release()`
  then the twin's `destroyNative()`.[^gpuview]
- **One frame per tick**: `OSLevelResourceDriver::tick()` pumps,
  `syncLayout()`, then `renderFrames()` per window. A twin that drives its
  own frames (`drivesOwnFrames()`) is only queued.
- **Four seam shapes, chosen by `SurfaceKind`:** `LAYER`, `GL_CONTEXT`,
  `VULKAN_SURFACE` (host lends a `VulkanSurfaceLender` via `GPUHost->vk`),
  `HOST_WINDOW` (engine drives the host's own window, `GPUHost->native_view`).
  `GPUEngineDriver::surfaceKind()` is read *before* `attach()`. `LAYER`: the
  engine hands back layer pointer bits and the window engine adopts them
  (Metal, and Vulkan through MoltenVK). `GL_CONTEXT`: the window engine
  mints a GL surface, passes it as `GPUHost->gl`, and the engine draws
  inside a context it never made (OpenGL). A kind a window engine cannot
  mint is `GPUViewException::unsupported()` — decided by enum, no package
  named. GTK hosts only `GL_CONTEXT`. The SDL stage host lends
  `VULKAN_SURFACE` on Linux.[^slice2][^slice3]
- **A host may lend a layer it owns** (`GPUHost->layer`); a LAYER engine
  adopts it and answers `layer_pointer 0`.
- **The schedule is `SchedulesFrames`.** GPUView and StagedWindow keep
  `RunsFrames` (same public methods). CPU canvases use the trait
  directly. `requestFrame()` / `queueNativeFrame()` stay the
  self-driving door.
- **`GLSurface` is three verbs.** `makeCurrent()` before a frame,
  `present()` after, `drawableSize()` in pixels. The host owns the
  context; the engine owns GL state. Mint order is native → surface →
  host → `attach()` → twin, because `GPUView`'s constructor takes the
  executor; `FakeWindow::mintGPU()` holds that order.
- **A self-driving twin runs the hook inside the pump.** `GtkGLArea`'s
  `render` signal calls `renderFrame()`; the tick only queues. On AppKit
  the tick drives, for Metal and GL alike.
- **Blending is real on every engine.** `capabilities()->blending` is true
  on OpenGL, Vulkan, Metal and SDL_GPU; the Painter still degrades on an
  engine that answers false.
- **Vulkan is `LAYER` on Darwin, `VULKAN_SURFACE` elsewhere.** On macOS
  `jovian/venusian-vulkan` mints (or adopts a lent) `CAMetalLayer` and
  answers the same pointer bits Metal does; elsewhere the host lends a
  `VkSurfaceKHR`. Engines today: `metal`, `opengl`, `vulkan`, `sdl3`.
- **CPU drawing is a sibling path.** [cpu-drawing](/cpu-drawing.md)
  holds the Drawing2D-not-Executor split, the five engines, and the
  canvas rules. `Affine` and `Geometry` stay shared with the Painter;
  this file keeps GPU decisions. Do not duplicate the engine table
  here.[^cpu-drawing]

# Not in this slice

Depth, embedded panels. Typesetter / GlyphAtlas / `text()` exist
under Drawing. (Slice 2 landed OpenGL on both boxes.
Slice 3 landed Vulkan on the Mac through MoltenVK. CPU engines landed
in Task 11.)

[^contracts]: Drawing contracts
[^painter]: Painter
[^gpuview]: GPUView
[^canvases]: CPU canvases
[^spec]: Slice 1 design
[^slice2]: Slice 2 design
[^slice3]: Slice 3 design
[^cpu-drawing]: CPU drawing
