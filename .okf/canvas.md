---
type: Architecture
title: Canvas
description: >-
  Canvasable: one lifecycle over a GPU view, a GPU or CPU stage, or an
  embedded display, plus engine() — any CPU or GPU engine on any of them.
  One draw hook, on-demand frames, output adapters, offscreen blit.
tags: [surface, canvas, drawing, lifecycle, engines]
status: draft
generated: { by: claude-opus-5/claude-code, at: "2026-09-18T23:00:00Z" }
sources:
  - id: contract
    resource: src/Surface/Contracts/Canvas/Canvasable.php
    title: Canvasable
  - id: canvas
    resource: src/Surface/Canvas/Canvas.php
    title: Canvas
  - id: outputs
    resource: src/Surface/Canvas/Outputs
    title: ViewOutput, StageOutput, DisplayOutput
  - id: drawswith
    resource: src/Surface/Contracts/Drawing/DrawsWith.php
    title: DrawsWith
  - id: gpucanvas
    resource: src/Surface/Drawing/GPUCanvas.php
    title: GPUCanvas
  - id: tests
    resource: tests/Canvas/CanvasTest.php
    title: Canvas tests
  - id: enginetests
    resource: tests/Canvas/CanvasEngineTest.php
    title: Canvas engine tests
---

# Use

```php
$canvas = Canvas::of($window->gpu('scene', 'metal', 0, 0, 640, 480));   // or a stage, or an embedded display

$canvas->engine('dirty')                                                 // optional: any CPU or GPU engine
    ->background(Color::hex('#000'))
    ->draw(function (Drawing2D $g, Frame $f) use ($white, $font) {
        $g->fillCircle(64.0, 32.0, 20.0, $white)->text('READY', 0.0, 0.0, $white, $font);
    })
    ->present();                                                         // one frame; animate() for a stream
```

Type-hint `Canvasable`. Ask `output()` for the concrete only for what its
kind alone has (window mail, `panel()`).[^contract]

# Two choices, not one

Where pixels go and what makes them are separate. `output()` is the first,
`engine()` the second, and neither constrains the other. That is the whole
reason the object exists — see **Engine** below.

# What it is, and is not

A Canvas is a **lifecycle**, not a drawer. It owns the output's single
`onDraw` hook and spends the frame on the sketch's hook and nothing else.
The hook gets the output's own `Drawing2D`, so a verb reaches the rasteriser
unchanged and damage is exactly what was inked.[^canvas]

No drawing verb lives on the Canvas. That is deliberate: ink outside a frame
has to be stored somewhere, and anything stored has to be replayed or
dropped per target kind — which is how a sketch ends up behaving differently
on a GPU view and a panel.

# Persistence belongs to the output

| Target | Between frames | What a sketch does |
|---|---|---|
| any GPU target, `nframes`, `paged` | cleared to the background before the hook | draw the whole scene each frame |
| `dirty`, `full`, `epaper` | pixels stay; background applies at attach and on `clear()` | draw only what changed, or `$g->clear()` first |

`background()` sets the clear colour and the target decides when it lands —
the contract on `DrawTarget::setClearColor` is the authority.

This is the one difference a sketch can see, and it is real: a panel that
keeps its pixels is why the `dirty` engine can send a 16-byte damage band
instead of a whole frame. A Canvas that hid it would have to clear every
frame, which would make every frame whole-surface damage.

# Frames

On demand. `present()` asks for one on the next tick; `animate()` turns
continuous on, `animate(false)` off. `draw()` only registers the hook — it
asks for no frames by itself, and replaces rather than stacks.

`drawing()` reaches the **rasteriser's** drawer outside a frame, for work
that is not ink: minting a texture, `textBounds()`, `size()`.

# Outputs

| Kind | show / hide | close | size |
|---|---|---|---|
| `GPU_VIEW` | view show / hide | `remove()` | view frame |
| `GPU_STAGE`, `CPU_STAGE` | `show()` / **unsupported** | `close()` | window points / canvas pixels |
| `EMBEDDED_DISPLAY` | panel switch, throws if not switchable | detach | panel pixels |

Keyed off contracts, never concretes. Verbs after close throw
`CanvasException::closed()`. `StagedWindow::isShown()` exists for this.[^outputs]

# Engine

`engine($name)` takes any CPU engine (`dirty`, `full`, `epaper`, `paged`,
`nframes`) or any GPU engine (`metal`, `opengl`, `vulkan`, `sdl3`), on any
of the four outputs. The output keeps its size, format, visibility and
lifecycle; only who draws changes. `rasteriser()` answers who that is —
`output()->engine()` does not, because a panel drawn by Metal still holds
finished pixels and still says `CPUEngine`.

A GPU engine needs no window: `GPUCanvas::headless()` attaches it to a
`GPUHost` with no native view and no lent layer, and `readPixels()` brings
the frame back. It answers `CPUDrawTarget`, so anything that consumes
finished pixels can take it.[^gpucanvas]

Two mechanics, picked by what the output is:

| Output | Mechanic |
|---|---|
| panel, CPU stage (`DrawsWith`) | takes the renderer outright — `drawWith()` swaps the canvas it presents[^drawswith] |
| GPU stage, GPU view | owns its surface, so the renderer draws offscreen at `size()` and arrives as one texture in the target's frame |
| GPU target, engine it already runs | drawn straight through; no offscreen at all |

Offscreen renderers draw RGBA8 (`ROW_MAJOR` / `B32`) at `size()`, not at
drawable pixels — coordinates then read the same in both mechanics, at the
cost of an upscale on a HiDPI window. The texture is minted and released
inside the frame that draws it. A headless GPU engine the Canvas attached is
released on `close()` or on the next `engine()`.

An engine that cannot take the format says so where it always did: `epaper`
on a GPU stage throws from the framebuffer, because an ePaper engine needs
an ePaper format.

# Rules

- `Surface\Canvas` imports `Surface\Contracts\*` only. Manifest requires
  `venusian-surface/contracts` and nothing else. No provider, no alias.
- A future CPU view (`OSView` + `CPUDrawTarget`) joins as one more match arm
  and one `CanvasKind` case; no contract change, and `engine()` already
  covers it — a `CPUDrawTarget` that is `DrawsWith` takes the renderer.
- Engines resolve through `app('cpu-engines')` / `app('gpu-engines')` in
  overridable methods, the same seam `Windowable::resolveGPUEngine()` uses,
  so the flow is provable without a container.

# Not here

Stage `hide()`, a bare headless `CPUDrawTarget` as an output, more than one
hook per canvas. No resize of a renderer: a swapped canvas keeps the size it
was minted at, and a GPU target's offscreen is not rebuilt when the window
changes size.

[^contract]: Canvasable
[^canvas]: Canvas
[^outputs]: ViewOutput, StageOutput, DisplayOutput
[^drawswith]: DrawsWith
[^gpucanvas]: GPUCanvas
[^tests]: Canvas tests
[^enginetests]: Canvas engine tests
