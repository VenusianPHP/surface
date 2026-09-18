---
type: Architecture
title: Stage — engine-owned windows
description: >-
  Whole windows an engine draws every pixel of. Hosts by alias, engines by
  alias, the same Drawing2D as a GPUView.
tags: [surface, stage, gpu, windows]
status: draft
generated: { by: cursor-grok-4.6/cursor, at: "2026-09-18T04:45:00Z" }
sources:
  - id: manager
    resource: src/Surface/Stage/StageManager.php
    title: StageManager
  - id: abstract
    resource: src/Surface/Stage/AbstractStage.php
    title: AbstractStage
  - id: staged
    resource: src/Surface/Stage/StagedWindow.php
    title: GPU StagedWindow
  - id: cpu-staged
    resource: src/Surface/Stage/CPUStagedWindow.php
    title: CPU StagedWindow
  - id: resource
    resource: src/Surface/Stage/StageResourceDriver.php
    title: Stage dock resource
  - id: tests
    resource: tests/Stage
    title: Stage tests
---

# Shape

`Stage::open($name, $engine, $w, $h, $host)` → host session (`stage.<host>`) →
engine (`gpu.<engine>`) → `StagedWindow` (the GPU class, a
`GPUStagedWindow`), hidden. `Stage::openCPU($name, $engine, $canvas, $w,
$h, $host, $fit)` is the same host session, then a CPU engine
(`cpu.<engine>`) and the `CPUStagedWindow` class. `Stage::emulate()` is
`openCPU` at `panel->width * $zoom` × `panel->height * $zoom` with
`INTEGER_SCALE`; a zoom below 1 is a `StageException`. A null `$fit`
reads `config('stage.cpu_fit')` (default `integer_scale`);
`config('stage.cpu_renderer')` defaults `software`. `show()` presents. Same
`onDraw(fn (Drawing2D $g, Frame $f))` as a GPUView. The
`StagedWindow` *contract* is engine-free (`DrawTarget` + window
verbs). `AbstractStage` implements that base: size and scale Surface
believes in, change-only resize mail, one close announcement,
release-before-destroy. The GPU class is `AbstractStage` plus
`RunsFrames`; host packages still fill `applyTitle` / `applyShow` /
`destroyNative`. `GPUStagedWindow` adds the executor;
`CPUStagedWindow` adds the canvas plus `fit()` / `canvasSize()`.
The CPU *class* (`Surface\Stage\CPUStagedWindow`) is `AbstractStage`
plus a fixed canvas: every `DrawTarget` / `CPUDrawTarget` verb
forwards to it; `applyPresent(string $rgba8)` is the one host hook.
`renderFrame()` is skipped while hidden; after a canvas frame it
presents. `show()` and a window resize present the same RGBA8
without running the hook or resizing the canvas.[^cpu-staged]
`StageFit` is `stretch` / `letterbox` / `integer_scale` / `overscan`.
`StageSession::open()` returns `GPUStagedWindow`. `openCPU()` is on
the session; the abstract default refuses with
`StageException::cpuUnsupported($host)` so AppKit does not have to
change to say no.[^manager][^abstract]

| Host | Package | Pump | Kinds it mints |
|---|---|---|---|
| `appkit` | jovian/venusian-appkit | shares NSApp with `os` | LAYER, GL_CONTEXT — GPU only; CPU refuses (`cpuUnsupported`) |
| `sdl3` | jovian/venusian-sdl3 | own (`SDLPollEvent`) | HOST_WINDOW, GL_CONTEXT, LAYER (mac), VULKAN_SURFACE (linux); CPU (`SdlCPUStagedWindow`) |
| `glfw` | roadmap | — | — |

# CPU stages

Two kinds sit on `AbstractStage`: the GPU class (`Surface\Stage\StagedWindow`)
and the CPU class (`Surface\Stage\CPUStagedWindow`). `Stage::openCPU()` is the
named door; `Stage::emulate()` is `openCPU` at `panel->width * $zoom` ×
`panel->height * $zoom` with `INTEGER_SCALE`. The canvas is fixed at mint —
`size()` / `scale()` are the window, `canvasSize()` / `flush()` / `rgba8()`
are the canvas. A resize rescales the picture, never the buffer. `StageFit`
is `stretch` / `letterbox` / `integer_scale` / `overscan`; hosts always
present with nearest-neighbour filtering so a 1-bit panel is not blurred.
`config('stage.cpu_renderer')` (`STAGE_CPU_RENDERER`, default `software`)
names the host renderer; `null` lets the host pick an accelerated blit.
A host that cannot present a CPU canvas refuses by contract:
`StageSession::mintCPUStage()` throws `StageException::cpuUnsupported`.
AppKit says no today; sdl3 mints.[^cpu-staged][^manager]

# Rules

- Surface names hosts by enum + alias only. A GLFW host is a new
  `StageSession` in a new package; no Surface change.[^manager]
- Engine start is lazy: first `connect()`, not container resolution.
- A staged window renders only once shown: `visible()` is open &&
  shown. The GPU class's `frameVisible()` delegates to that. Stages mint
  hidden; `StageManager::open()` never auto-shows. `show()` calls
  `applyShow()` then `presented()` — a no-op on the GPU class.[^abstract][^staged]
- A CPU stage presents from `canvas->rgba8()` after a canvas frame that
  ran, on `show()`, and on resize. It does not keep a copy of the last
  frame. Resize does not remint the canvas.[^cpu-staged]
- `resized()` acts on real changes only: store the new size, call
  `applyResize()`, then mail `stage.resized.<name>`. The GPU kind
  resizes the executor in pixels and requests a frame (an on-demand
  stage would otherwise show stale content).[^abstract][^staged]
- `close()` is terminal and idempotent: `releaseEngine()` in a `try`
  (GPU: the executor) → `destroyNative()` in a `finally` (a throwing
  release still destroys the native) → announce `stage.closed.<name>`
  once.[^abstract]
- `closeRequested()` announces `stage.closed.<name>` once and leaves the
  window open; it latches only when the mail was actually pushed, so a
  request that arrives before `setPool()` is not spent.[^abstract]
- Loop: dock resource `stage.<host>` pumps (skipped for AppKit when `os`
  is on the dock), then one frame per open stage.
- A host that owns the native pump (`ownsNativePump()`: SDL on macOS)
  drains the OS queue itself; the `os` resource skips its NSApp drain and
  hands that host the tick's idle wait. SDL reads keys only inside its own
  pump and forwards every event to AppKit, so native windows keep working. Pump never waits; the
  `os` resource owns the tick's idle wait. Linux: an SDL event can sit up
  to one tick budget behind GTK's poll.[^resource]
- A host's engine `attach()` failure is a `StageException`
  (`attachFailed`: host + engine named, engine exception as previous);
  a `StageException` passes through unwrapped.
- Teardown: `StageManager::destroy()` closes every stage, then
  disconnects every host session it created; a failure does not spare
  the rest, the first is rethrown. `LiveApplication::destroy()` calls it
  before the windows go. `StageSession::disconnect()` ends disconnected
  even when the engine hook throws.[^manager][^tests]
- Native windows and stages coexist in one process on both boxes
  (probe: Mac AppKit + SDL3, Pi GTK + SDL3). No exclusivity guard. An SDL
  stage's video driver follows what libSDL3 was built with (Wayland,
  X11, or neither); coexistence with GTK holds either way because the
  display connections are separate.

# Not here

Text (`Surface\Fonts`) — see [components-to-come](/components-to-come.md).
Input is in — see [human-input](/human-input.md).

[^manager]: StageManager
[^abstract]: AbstractStage
[^staged]: GPU StagedWindow
[^cpu-staged]: CPU StagedWindow
[^resource]: Stage dock resource
[^tests]: Stage tests
