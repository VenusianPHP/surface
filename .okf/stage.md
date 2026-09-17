---
type: Architecture
title: Stage — engine-owned windows
description: >-
  Whole windows an engine draws every pixel of. Hosts by alias, engines by
  alias, the same Drawing2D as a GPUView.
tags: [surface, stage, gpu, windows]
status: draft
generated: { by: claude-opus-5/claude-code, at: "2026-09-14T00:00:00Z" }
sources:
  - id: manager
    resource: src/Surface/Stage/StageManager.php
    title: StageManager
  - id: staged
    resource: src/Surface/Stage/StagedWindow.php
    title: StagedWindow abstract
  - id: resource
    resource: src/Surface/Stage/StageResourceDriver.php
    title: Stage dock resource
  - id: tests
    resource: tests/Stage
    title: Stage tests
---

# Shape

`Stage::open($name, $engine, $w, $h, $host)` → host session (`stage.<host>`) →
engine (`gpu.<engine>`) → `StagedWindow`, hidden. `show()` presents. Same
`onDraw(fn (Drawing2D $g, Frame $f))` as a GPUView.[^manager]

| Host | Package | Pump | Kinds it mints |
|---|---|---|---|
| `appkit` | jovian/venusian-appkit | shares NSApp with `os` | LAYER, GL_CONTEXT |
| `sdl3` | jovian/venusian-sdl3 | own (`SDLPollEvent`) | HOST_WINDOW, GL_CONTEXT, LAYER (mac), VULKAN_SURFACE (linux) |
| `glfw` | roadmap | — | — |

# Rules

- Surface names hosts by enum + alias only. A GLFW host is a new
  `StageSession` in a new package; no Surface change.[^manager]
- Engine start is lazy: first `connect()`, not container resolution.
- A staged window renders only once shown: `frameVisible()` is open &&
  shown. Stages mint hidden; `StageManager::open()` never auto-shows.[^staged]
- `resized()` acts on real changes only: resizes the executor in pixels,
  requests a frame (an on-demand stage would otherwise show stale
  content), then mails `stage.resized.<name>`.[^staged]
- `close()` is terminal and idempotent: release the executor →
  `destroyNative()` in a `finally` (a throwing release still destroys the
  native) → announce `stage.closed.<name>` once.[^staged]
- `closeRequested()` announces `stage.closed.<name>` once and leaves the
  window open; it latches only when the mail was actually pushed, so a
  request that arrives before `setPool()` is not spent.[^staged]
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
[^staged]: StagedWindow abstract
[^resource]: Stage dock resource
[^tests]: Stage tests
