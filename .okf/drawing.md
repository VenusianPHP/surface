---
type: Architecture
title: GPU drawing — contracts, Painter, GPUView
description: >-
  How a sketch draws into a native window with a GPU: engine-free contracts,
  one Painter that batches shapes into vertices over any Executor, and the
  GPUView that runs one frame per tick after layout.
tags: [surface, drawing, gpu, contracts, views]
status: draft
generated: { by: cursor-grok-4.6/cursor, at: "2026-09-17T21:00:00Z" }
sources:
  - id: contracts
    resource: src/Surface/Contracts/Drawing
    title: Drawing contracts
  - id: painter
    resource: src/Surface/Drawing/Painter.php
    title: Painter
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
---

# Overview

`$window->gpu('scene', 'metal', x, y, w, h)->onDraw(fn (Drawing2D $g, Frame $f) => ...)`.
Surface names the engine, resolves it through `GPUEngineManager`
(`gpu-engines`, alias `gpu.<engine>` from `config/gpu.php`), and the window
engine mints the host through `mintGPU()`. Everything Surface-side is
fake-provable; the engine lives in `jovian/venusian-<engine>`.[^contracts]

`DrawTarget` is engine-free (hook, clear, continuous, redraw, size,
`renderFrame`). `GPUDrawTarget` adds `engine()` / `executor()` —
`OSGPUView` and `StagedWindow` extend it. `CPUDrawTarget` /
`PagedDrawTarget` / `CPUHost` / the five `CPUEngine` cases (`dirty` /
`full` / `epaper` / `paged` / `nframes`) are on the contracts; the
rasterizer, canvases and engines are the rest of this slice.

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
- **The frame loop is `RunsFrames`**, shared by GPUView and StagedWindow.
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

# Not in this slice

Text, depth, embedded panels. (Slice 2 landed OpenGL on both boxes.
Slice 3 landed Vulkan on the Mac through MoltenVK.)

[^contracts]: Drawing contracts
[^painter]: Painter
[^gpuview]: GPUView
[^spec]: Slice 1 design
[^slice2]: Slice 2 design
[^slice3]: Slice 3 design
