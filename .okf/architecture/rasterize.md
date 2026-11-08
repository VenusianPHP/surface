---
type: Module
title: Rasterize
description: Shapes into coverage spans, hard-edged or anti-aliased, geometry in PHP (native) or C (extended, ext-rasterize).
resource: src/Surface/Rasterize/
tags: [surface, rasterize, spans, antialiasing]
status: draft
generated: { by: claude-opus/5.5, at: 2026-11-07T23:00:00Z }
sources:
  - id: contracts
    resource: src/Surface/Contracts/Rasterize/
    title: Rasterizer, Scanner, RasterizeDriver, Edges, FillRule
  - id: shapes
    resource: src/Surface/Rasterize/Rasterizer.php
    title: The shared shape layer and Stroker
  - id: scanner
    resource: src/Surface/Rasterize/Native/NativeScanner.php
    title: NativeScanner, the arithmetic ext-rasterize mirrors
---

# Overview

Rasterize = shapes → coverage spans. Geometry only, never a pixel. Needs no framebuffer, no engine. Used by the software engine only; GPU engines rasterise on the GPU.[^contracts]

```php
$fb = app('framebuffers')->driver('extended')->full(FormatSpec::rgba8(), 320, 240);
$raster = app('rasterize')->driver('extended')->rasterizer(Region::wholeSurface(320, 240), Edges::ANTIALIASED);
$fb->paintSpans($raster->fillEllipse(160, 120, 80, 50), 0xFF6600FF);
```

Meets Framebuffers only at span bytes (`Spans`, 7 bytes: y, x, length, coverage). Neither requires the other.

# Structure

| Layer | Class | Does |
|---|---|---|
| Shapes | `Rasterizer` (abstract) | checks input (finite, ≤ 2²⁴), picks thin line or outline, `Stroker` contours, calls its scanner |
| Row work | `Scanner` | `path`, `ellipse`, `ring`, `roundRect`, `roundRing`, `shadow`, `polyline` → spans; `batch(ops)` many in one call |
| Flavor | `NativeRasterizer` / `ExtendedRasterizer` | only `mint()` the scanner |

| Driver | Scanner | Geometry |
|---|---|---|
| `native` | `NativeScanner` | PHP |
| `extended` | `ExtendedScanner` over `RasterScanner` | C (ext-rasterize 0.10); detected by `class_exists(RasterScanner::class)` |

Shapes: `fillRect`, `strokeRect`, `line`, `polyline`, `fillTriangle`, `fillPolygon` (any polygon), `fillPath` (contours: holes, unions), `fillEllipse`, `strokeEllipse`, `fillRoundRect`, `strokeRoundRect`, `shadow`. Circles = ellipses.

Batches: `Rasterizer::fillBatch(shapes, Framebuffer)` takes shape commands as RenderingEngine records them (`path`, `polyline`, `ellipse`, `ring`, `roundrect`, `roundring`, `shadow`; the clip after the colour is not read), lowers each exactly as its method would, scans them all with one `Scanner::batch()` and paints each non-empty answer with `paintSpans()` at its colour, in order. A bad shape throws before any of the batch is painted. A scanner op is `[Scanner::PATH…POLYLINE, ...the method's arguments]`, a path's rule as 0 (non-zero) or 1 (even-odd); the numbers are ext-rasterize's `RASTER_OP_*`, so `ExtendedScanner::batch()` is one `RasterScanner::batch()` call. The single-shape methods lower to the same op and scan it alone. Direct fill: `fillBatch(shapes, target, direct: true)` on the extended rasterizer, when `RasterScanner::fills()` (ext-fb loaded, exporting its span sink) and `$target->spanSink()` is an `FbBuffer`, scans and paints in one `RasterScanner::fill()` call, no span strings in PHP, and reports each op's bounds with `$target->paintedSpans()`; otherwise, or with `direct: false`, the spans path. Same pixels and damage (`tests/Rasterize/DirectFillTest.php`).

Rounded shapes:[^scanner]

* Radii `[tl, tr, br, bl]`, each held to `0..min(w, h)/2` by `Rasterizer`; not a list of four numbers → `RasterizeException`.
* `roundRect`: one interval a sample line; an end inside a corner's band is on its quarter circle, `cx ∓ r·sqrt(1 − t²)`, written as the ellipse is, so a box that is a circle answers the ellipse's bytes.
* `strokeRoundRect` (`roundRing`): the band between the box and the box inset by the stroke, inner radii `max(0, r − stroke)`; the whole box when the inset box is empty.
* `shadow`: coverage `1 − smoothstep(−blur, blur, d)` sampled once at each pixel centre in both edge modes, `d` the signed distance to the box rounded by `radius`, `smoothstep = t·t·(3 − 2t)`, `t = clamp((d + blur) / 2·blur, 0, 1)`. Blur ≤ 0: the hard box, in where `d < 0`. Hard edges: coverage ≥ 128 → 255. Columns more than `radius + blur + 2` inside both sides depend on y alone: one sample, one run. Transforms are the caller's: rotated shapes arrive as polygons.[^shapes]

# Edges

Pixel `(x, y)` covers `[x, x+1) × [y, y+1)`; integer coordinates are pixel edges (one-wide stroke on y = 10 straddles rows 9 and 10; on 10.5 fills row 10).

* `HARD`: one sample per row at pixel centres; centre on left/top edge in, right/bottom out. Coverage 255.
* `ANTIALIASED`: 16 sample lines per row, exact horizontal overlap per pixel, summed; coverage 1..255; equal neighbours join.
* Both modes walk only the rows and columns of the shape's extent (a pixel more each side, cut to the clip), not the whole clip: a shape's cost follows its own size, and outside the extent no sample line has an interval, so the spans are the same.
* `ANTIALIASED` rows are emitted run by run: the scanner keeps the columns a row's sample lines wrote; between two of them coverage and delta are 0, so every pixel holds the running value and the stretch is one span. A large shape's cost follows its edges, not its area.
* Thin hard lines (stroke ≤ 1): one-pixel lines between floored endpoints, every pixel once. Everything else: outlines filled non-zero; butt ends; miter ≤ 4 half-strokes, bevel past it; contours turned one way so pieces unite.
* `strokeRect` above thin = outer minus inner rect, even-odd. Stroked ellipse = ring between `r ± stroke/2`.

# Parity

Arithmetic: IEEE double, `+ − × ÷ sqrt floor ceil`, one fixed order in both drivers; ext built `-ffp-contract=off` (clang otherwise fuses multiply-adds). No trig in Rasterize. Result: identical bytes; hand-worked goldens + seeded `ParityTest` enforce.[^scanner]

Limits: shapes ≤ 2²⁴ (`Rasterizer::LIMIT`), leaving room for derived points under the scanner's 2³⁰ (`Scanner::LIMIT`), where one-pixel line arithmetic still fits 64-bit integers. Clip inside 0..65535.

[^contracts]: `Surface\Contracts\Rasterize`; span format in `Surface\Contracts\Framebuffers\Spans`.
[^shapes]: `Stroker::outline()` is the one stroker; runs in PHP for both drivers.
[^scanner]: `NativeScanner` and ext-rasterize's `core.c` keep the same steps in the same order: change one, change both.
