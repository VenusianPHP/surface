---
type: Module
title: Drawing
description: "Rendering engines: one draw API over a framebuffer, frames recorded then drawn, DrawingManager, VelvetGE the software engine, GpuRenderingEngine over a package's device."
resource: src/Surface/Drawing/
tags: [surface, drawing, rendering, velvet]
status: draft
generated: { by: claude-opus/5.5, at: 2026-11-08T12:30:00Z }
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
* Scroll: `canScroll()` = `keepsFrame`. `scroll(region, dx, dy)` between frames (open frame or `canScroll()` false → `DrawingException`) runs `Framebuffer::scroll()` and queues the clipped region for the next `end()`: a frame without leading clear damages its boxes + the region; a clearing frame damages what changed + the region (the region no longer holds the last frame) and is scissored to that.
* `invalidate()`: framebuffer written outside the engine.
* Text spans run top to bottom: trimming halves to the first row.

| Bench (20 frames, Mac) | native before → after | C before → after | changed |
|---|---|---|---|
| 240×240 RGB565 clock | 111.6 → 32.4 ms | 0.97 → 1.02 ms | 8.7% |
| 240×480 page, 2400 chars + counter | 89.8 → 30.8 ms | 28.3 → 30.6 ms | 0.1% |

# State

`push` / `pop` (transform + clip) · `translate` · `scale` · `rotate` (+x → +y, clockwise on screen) · `transform(Affine)` · `clip(?Region)` in framebuffer pixels, held to the surface. Canvas order: last transform called is the first a point goes through. `clear()` ignores clip.

# Verbs

`clear` · `fillRect` · `strokeRect` · `line` · `polyline` · `fillTriangle` · `fillPolygon` · `fillPath` · `fillEllipse` · `strokeEllipse` · `roundRect` · `shadow` · `image`. `Color` per call. Zero/negative size, radius, stroke, opacity: nothing. Non-finite, or past 2²⁴ once transformed: `DrawingException`.

Commands the base hands an engine's `execute()`:[^base]

| Command | From |
|---|---|
| `['clear', rgba]` | `clear` |
| `['path', contours, FillRule, rgba, clip]` | rects, triangles, polygons, paths; turned ellipses |
| `['polyline', points, stroke, closed, rgba, clip]` | lines, polylines, stroked rects; turned stroked ellipses |
| `['ellipse', cx, cy, rx, ry, rgba, clip]` | ellipse under translate + scale |
| `['ring', cx, cy, rx, ry, stroke, rgba, clip]` | stroked ellipse under translate + scale |
| `['image', Framebuffer, Affine, 1..255, Filter, clip]` | `image` |
| `['roundrect', x0, y0, x1, y1, [tl, tr, br, bl], rgba, clip]` | `roundRect` fill under an upright, even transform; with a border, the box inset by it, radii `max(0, r − border)`, left out when the border reaches the middle |
| `['roundring', x0, y0, x1, y1, [tl, tr, br, bl], inset, rgba, clip]` | `roundRect` border, inside the rect |
| `['shadow', x0, y0, x1, y1, radius, blur, rgba, clip]` | `shadow`, the box already moved by the caller's offset |
| `['spans', bytes, rgba, clip]` | whole-pixel opaque bitmap `text` |
| `['glyphs', TrueTypeFont, pixelSize, [[face, glyph, maskLeft, maskTop, style], …], rgba, box, clip]` | TrueType `text` upright at an even scale; box = union of the masks, no grow |

Stroke width × `sqrt(|det|)`. Turned ellipse → polygon within 0.1 px (`π·sqrt(r / 0.2)` sides, 12..1024). Trig lives here, never in Rasterize.

`roundRect(x, y, w, h, [tl, tr, br, bl], fill, ?border, borderWidth)`: radii held to `0..min(w, h)/2`; not four numbers → `DrawingException::badRadii()`. `shadow(x, y, w, h, radius, blur, color, dx = 0, dy = 0)`: the rounded box moved by (dx, dy) in the box's own units, before the transform; blur < 0 as 0. Upright and even = `b = c = 0`, `a = d > 0`: coordinates in framebuffer pixels, radii, inset and blur × `a`. Boxes: `roundrect` / `roundring` the extent + 1, `shadow` the extent + blur + 1.

Limits:[^base]

* A rounded rect under a turn, shear or uneven scale is a polygon path, each corner a quarter of the ellipse rule's sides: fill `NON_ZERO`, border two contours `EVEN_ODD`.
* A shadow under a turn, shear or uneven scale throws `DrawingException::unevenShadow()`: a blurred turned box needs its distance in the box's own frame.

# Text

`text(string, x, y, Color, Font)`, `textBounds(string, Font)`: bitmap and TrueType faces, drawn under the transform and clip. Bitmap: whole-pixel opaque text lowers to a `spans` command (no rasterising); the rest to one `path`. TrueType: upright at an even scale lowers to one `glyphs` command; the rest to one `path` of the glyph outlines. See [Fonts](fonts.md).

# VelvetGE

Software engine, `velvet`. `new VelvetGE(Framebuffer, RasterizeDriver, ?Edges)`. Command → Rasterize spans → `paintSpans()`; a run of shape commands (`path`, `polyline`, `ellipse`, `ring`, `roundrect`, `roundring`, `shadow`) whose clips cut to the same rows is drawn by one `Rasterizer::fillBatch()` (one scan call), the run ending at any other command or another clip; a clip's Region is cut to the rows once for the commands that share it, and its rasterizer is kept by `[x][y][width][height]` for the frame. A bad shape throws before any shape of its run is painted; `new VelvetGE(..., directFill: true)` lets the extended rasterizer paint runs straight into the framebuffer (Velvet arg / config `drawing.velvet.direct_fill`, `'auto'` default or `'off'`, env `VELVET_DIRECT_FILL`); `roundrect` / `roundring` / `shadow` → `fillRoundRect` / `strokeRoundRect` / `shadow`;  `glyphs` → the glyph cache's masks → one `paintMasks()`; image → `paintImage()`; clear → `fill()`. Edges default: anti-aliased where format blends (RGB, grey), hard otherwise.[^velvet]

Framebuffer kind = mode:

| Kind | Frame |
|---|---|
| full, dirty, ePaper | drawn over what is there; dirty epoch left to the drainer |
| ring | `repair()`, draw, `present()` |
| paged | drawn into current page, only its rows rasterised; holder: `setPage(0)` + `frame()` + drain, then `setPage(n)` + `replay()` + drain. Pages = same frame on a full buffer, byte for byte |

Measured (Mac, 400×340 RGBA8, 30 bordered rounded rects radius 8 + 30 shadows blur 12, whole frames): 4.98 ms both extended · 177 ms native rasterize, extended framebuffer.

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
| `[ROUNDRECT, first, x0, y0, x1, y1, [tl, tr, br, bl], rgba]` | quad 1 px past the box; coverage per sample inside the rounded box |
| `[ROUNDRING, first, x0, y0, x1, y1, [tl, tr, br, bl], inset, rgba]` | same quad; coverage per sample inside the box and outside the box inset by `inset`, radii `max(0, r − inset)` |
| `[SHADOW, first, x0, y0, x1, y1, radius, blur, rgba]` | quad blur + 1 past the box; coverage per pixel centre `1 − smoothstep(−blur, blur, d)`; hard edges ≥ 128 → 255 |
| `[ATLAS, atlas, width, height]` | make texture `atlas`, 8-bit single channel, all zero; kept across frames until the next `ATLAS` for it or `release()` |
| `[MASK, atlas, Region, bytes]` | write width × height coverage bytes, rows top-down, into that region of the atlas |
| `[GLYPHS, first, count, texels, atlas, rgba]` | count vertices of quads; atlas texel pairs start at vertex `texels`; coverage sampled nearest, blended as `paintSpans()` blends it |

Lowering: `clear` → `CLEAR`, or `SCISSOR` + `SOLID` for a region · `path` → `STENCIL_FILL` + `COVER` (contour of n points = n − 2 triangles from its first point, cover = box floor/ceil) · `polyline` → `Stroker::outline()` then as path, non-zero · `ellipse` / `ring` → one quad a pixel past the shape · `image` → `UPLOAD` once a frame a source, `IMAGE` with the inverse placement · `spans` → `RECTS` · `roundrect` / `roundring` / `shadow` → one `ROUNDRECT` / `ROUNDRING` / `SHADOW` quad, as an ellipse · `glyphs` → `GLYPHS`, after a first pass hands every mask of the frame to `GlyphAtlas::prepare()`, so all `ATLAS` / `MASK` ops come before any draw. `SCISSOR` only when the clip changes.

Velvet's glyph cache (one per engine): key `face|pixelSize|glyph|style|edges`, value the mask's coverage string and width, made once by `TrueTypeFont::mask()` (hard edges: 128 and up → 255, else 0, applied then), a glyph with no coverage kept as nothing. Capped at `VelvetGE::GLYPH_CACHE_BYTES` (8 MiB, each entry 64 bytes more), least recently used out first; it lives as long as the engine. One string cache serves every store the engine draws into (target and layer scratch frames, native or extended). Translucent text blends twice where two glyph boxes overlap (masks paint in order), as before the cache.

`GlyphAtlas` (one per `GpuRenderingEngine`, its edges; `forget()` on `release()`): shelves with 1 px between masks, key `face|pixelSize|glyph|style`, a mask uploaded once across frames. A frame whose new masks do not fit resets the atlas at its start; one that alone does not fit doubles it, up to 4096, then `DrawingException::atlasFull()`. Hard edges threshold at 128 before upload. A `glyphs` command lowered without an atlas → `DrawingException::noAtlas()`.[^gpu]

GpuParity (`tests/Support/GpuParity/GpuParity.php`) holds every engine package to Velvet's pixels, scene by scene: a scene is `[exact, frame, tolerance?]`. Exact scenes match every byte; lenient ones (`'rounded rects'`, as ellipses) excuse edge pixels; `'shadows'` carries a tolerance of 1 per channel at every pixel, since float32 coverage on the GPU can round a step apart from Velvet's double.

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
[^gpu]: `Surface\\Drawing\\Gpu`: `GpuRenderingEngine`, `GpuDevice`, `DrawList`, `Op`, `Lowering`, `GlyphAtlas`.
[^manager]: Not a Voyager `Manager`: engines are built per call from arguments, never cached.

## Paths and curves

`Surface\NutsAndBolts\Path` is a builder as SVG path data, Canvas 2D and Skia give: `moveTo`, `lineTo`, `quadTo`, `cubicTo`, `close` (a segment after `close()` starts a new subpath at the start it closed, as Canvas does); `transformed(Affine)`; `flatten($tolerance = 0.25)` splits a curve in halves (de Casteljau) until its control points lie within the tolerance of the chord segment, to 2^16 pieces, by one fixed rule. `fill(Path, Color, FillRule)` and `stroke(Path, Color, StrokeStyle)` put the path through the transform and flatten it within a quarter pixel, so every engine receives the same polygon: both lower to the existing `path` command, and no engine has a curve of its own. `StrokeStyle` is SVG's width, cap (`StrokeCap` butt, round, square), join (`StrokeJoin` miter, round, bevel), miter limit, dash and dash offset. A stroke needs a transform that scales x and y alike and does not shear (a turn, a mirror or a uniform scale: the stroke is the same in device space), else `DrawingException::unevenStroke()`. `Stroker::outline()` builds the outline (caps, joins, miter limit), `Stroker::dashed()` cuts a polyline into dashes first. A subpath that was only moved to draws nothing; one that was drawn but has no length draws a dot under a round cap and a square (turned to the path's direction, else along x) under a square cap, as SVG 2 says, and so does a dash element of length 0 (a dotted line from `[0, 6]` with round caps). A closed dashed subpath is walked once round: a dash that runs across its start is one piece joined there, and a dash that never stops is the whole subpath, closed. Measure, as SVG's getTotalLength()/getPointAtLength() and Skia's SkPathMeasure: `Path::length()`, `pointAt($distance)` (point and direction), `segment($from, $to)` (the part between two distances, made of the same kind of pieces: a curve gives the curve between those two parameters by de Casteljau, as `SkPathMeasure::getSegment()` does), measured on a polyline that follows each curve within 0.05; `pointAt` is on the curve itself and its direction is the tangent.

## Gradients

`fill()` and `stroke()` take a `Color` or a `Gradient`: `LinearGradient(x0, y0, x1, y1, stops, spread)` and `RadialGradient(cx, cy, radius, stops, spread)` in `Surface\NutsAndBolts`, as SVG's linearGradient and radialGradient, Canvas 2D's createLinearGradient()/createRadialGradient() and Skia's gradient shaders are. Stops are `[offset 0..1, Color]`, offsets not falling (equal offsets make a hard edge: the later stop wins); `GradientSpread` is pad, repeat or reflect. The stops are baked into a 256-entry RGBA8 ramp (`Gradient::ramp()`: straight alpha interpolated per channel, rounded to a byte); a pixel's centre goes through `toGradientSpace($transform)` (the matrix from the surface into the gradient's own space, where a linear gradient runs from (0, 0) to (1, 0) and a radial one has radius 1) to a position t, spread applied, and takes the ramp entry `floor(t × 255 + ½)`. The path is recorded as `['gradpath', contours, rule, ['kind', 'inverse', 'spread', 'ramp'], clip]`. Velvet hands the path's coverage spans, the ramp, the kind, the spread and the inverse to `Framebuffer::paintGradient()`: the store works each span's colours out itself (native PHP, or ext-fb's `FbBuffer::paintGradient`, byte-identical; the position is computed at a span's first pixel and stepped along it), and VelvetGE holds no pixel loop; the GPU lowering emits the stencil fill, an UPLOAD of the ramp as a 256 x 1 source, and `Op::COVER_GRADIENT` (see DrawList). A gradient stroke is the outline of the stroke, filled in the gradient.

Filters. `Framebuffer::filter(FilterChain, ?Region)` replaces a region's pixels with a chain run over them. A `FilterChain` (`Contracts/Drawing/Effects`) is a list of steps in the SVG filter model: a step reads `source`, a named earlier result or the previous result, and may name its own. Steps: `Blur` (gaussian by SVG's three-box rule, below a deviation of 2 a Gaussian kernel of integer weights; or spiral, `samples` points on a golden-angle disc, bilinear at 1/256), `ColorMatrix` (20 values; saturate, hueRotate, luminanceToAlpha, brightness), `Flood`, `Composite` (over, in, out, atop, xor, arithmetic), `Blend` (normal, multiply, screen, darken, lighten), `Noise` (SVG turbulence and fractal noise with a seed, or an integer hash) and `Displace` (a map framebuffer moves each pixel; `bands` 3..16 samples along the displacement, weighted per channel, give dispersion). Pixels in and out are straight RGBA8; the steps work on premultiplied RGBA8, rounding to nearest. Noise is a function of the surface position and the seed, so a region filters the same bytes wherever it sits. The pixel work is the framebuffer driver's: `NativeFilters` for native stores, `FbBuffer::filter()` (ext-fb, `src/filter.c`) for extended ones, byte-identical (`tests/Framebuffers/FilterTest.php`); `FilterSteps` turns a chain into the arrays the extension takes. VelvetGE only calls `filter()`.

Layers. `layer($x, $y, $w, $h, $draw, $opacity, $radii, $filter, $backdrop)` draws a group apart and lays it down in one piece (SVG's group with opacity, CSS's stacking context, Skia's saveLayer). The box is [x, y, w, h] under an upright, even transform; `$draw($engine)` records the children, clipped to the box. Recorded as `['layer', $full(Region), [x0, y0, x1, y1, radii], $children, $alpha, $filter, $backdrop, $clip]` (the last is cut by the damage scissor, `$full` never is). Velvet (`VelvetGE::layDown()`): with a `$backdrop` chain it reads the framebuffer's pixels under the box and a margin of the chain's `reach()` (`flushRegion`), writes them to a scratch RGBA8 frame (`Framebuffer::scratch()`, the same driver kind), runs `Framebuffer::filter()` over them and lays the box back through the rounded coverage (`paintImage` with a mask); then the children are run into a second scratch frame over nothing (the store's blend leaves premultiplied RGBA8 there), `filter($filter, premultiplied: true)` runs the layer's own chain (or an empty one, which only straightens), and `paintImage` lays the result down at the opacity through the same coverage. Chains work in device pixels. Damage: a layer is a command with the box of its `$full`; a layer holding an image or a displacement map is redrawn every frame; for a layer with a backdrop the frame's damage grows to the whole read area whenever anything in it changed (`withBackdrops()`, repeated until no further layer's area is reached). A paged framebuffer cannot draw layers. On the GPU engines `Lowering::layer()` emits a layer as `TARGET` (a layer target of its own, RGBA8 the size of the frame, cleared), the children's operations, `TARGET` back, `FILTER` (the chain over the box, in place: `FilterPlan` turns it into passes, premultiplied in, straight out) and an `IMAGE` through the rounded box at the opacity (the `LAYER_SOURCE` operation makes the target the image's source); a backdrop is `BACKDROP` (copy the region of the target the layer lies on), `FILTER` and `IMAGE` first. `FilterPlan` works out every number a pass needs with `NativeFilters`'s functions (box sizes, kernel weights, spiral offsets, band weights, noise lattice), so a device holds only each pass's arithmetic; the GPU engines read a pass's extra numbers from an RGBA8 texture holding float32 bytes. Measured on opengl, vulkan, metal and sdl3 (Mac) and opengl and vulkan (gamingpc): every layer and filter scene is equal to Velvet's pixel for pixel, float filters included (the rounded-layer scene differs only at the rounded edge, like every rounded shape).

Glass. `glass($x, $y, $w, $h, $radii, Glass $glass, $opacity)`: a pane over what is drawn under its rounded box so far (`Surface\Contracts\Drawing\Effects\Glass` holds the per-pixel steps: blur, tint, saturation, light, rim, bevel, refraction, grain, frost, and the pane's shadow). The recorded command is `['glass', Region area, [x0, y0, x1, y1, [radii]], look[9], blurPx, shadow|null, clip]`; the shadow is a `shadow` command the engine paints after the backdrop is read and before the pane, so rounded corners meet it with nothing cut out. A blur under 0.75 device pixels draws the sharp backdrop (frost 0); the blur limit is 1024 device pixels. `GlassGroups::of($commands, $surface, $box)` splits a frame's panes into groups that share one backdrop capture: a pane joins the group while nothing drawn since (any command's dirty box, earlier panes and shadows included) touches the area it reads. Velvet (`VelvetGE::backdrop()`): at a group's first pane the union of its panes' boxes grown by `Glass::margin(blur, refraction)` is read once, taken down `Glass::levels(blur)` dual-filter levels (blur ≤ 3 → 1, ≤ 7 → 2, ≤ 15 → 3, else 4; the Kawase steps of ext-fb's `kawaseDown`/`kawaseUp`, byte-identical to `NativeGlass`) and up to 1/2 for each depth in use; each pane is one `Framebuffer::paintGlass()` over its box, sampling the half-size level (and the capture, for frost < 1). Levels are RGBA8 scratch frames kept by role and size; a pyramid is reused while the frame's damage misses its region. A paged framebuffer cannot draw glass. GPU engines (devices that implement the `DrawsGlass` marker): `Lowering` emits, per group, `Op::GLASS_BACKDROP [slot, Region, depths, n, keep]` (read that region of the target once, take it down and up the pyramid; with `keep`, the device reuses the pyramid it made for the n-th backdrop of the last frame, which Lowering allows when the region and depths are unchanged and no damage touches it) and, per pane, `Op::GLASS [first, geometry, look, depth, Region]` (one draw in the normal pass: the pane's per-pixel steps from the half-size level and the sharp capture). Each device pools its level textures by size and keeps pyramids across frames. A device that is not `DrawsGlass` gets the pane as a backdrop layer (blur, saturation, tint, light, opacity; no refraction, rim or grain; the shadow is painted before its layer).

Reuse (G3). OpenGL writes each frame's vertices into a ring of three buffers, and SDL3 into one buffer and one transfer buffer written with cycle on; Vulkan fills a pooled device-local buffer with `vkCmdUpdateBuffer` in the frame's command buffer; Metal makes a buffer for each frame, because ext-metal has no write into a buffer. Image sources and gradient ramps (`Op::UPLOAD`) are uploaded into textures pooled by size (and kind) that a frame takes from the pool and gives back when it commits or its fence signals. Nothing a frame may still read is written before that frame has completed. Measured with the real gallery at scale 2, the device's share of a frame is 1 to 4 ms of 20 to 110 ms of CPU, so these cut allocations but not the frame's cost; the rest is the widgets' painting and the lowering's PHP. A path cache keyed by contour content costs more to hash than the fan triangulation it would save (1.7 ms to 0.8 ms for 811 contours), and a cache by `Path` identity has nothing to key on, because the engine flattens a path as it records it: neither was built. G1 (instanced batches) and G2 (strokes on the GPU) are parked: the device's `draw()` is 1 to 3 ms of a 20 to 100 ms frame, and the rest is Silk's painting and recording (see the status section of the drawing-path performance spec).
