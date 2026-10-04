---
type: Module
title: Rasterize
description: Shapes into coverage spans, hard-edged or anti-aliased, geometry in PHP (native) or C (extended, ext-rasterize).
resource: src/Surface/Rasterize/
tags: [surface, rasterize, spans, antialiasing]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-03T23:06:29Z }
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
| Row work | `Scanner` | `path`, `ellipse`, `ring`, `polyline` → spans |
| Flavor | `NativeRasterizer` / `ExtendedRasterizer` | only `mint()` the scanner |

| Driver | Scanner | Geometry |
|---|---|---|
| `native` | `NativeScanner` | PHP |
| `extended` | `ExtendedScanner` over `RasterScanner` | C (ext-rasterize 0.10); detected by `class_exists(RasterScanner::class)` |

Shapes: `fillRect`, `strokeRect`, `line`, `polyline`, `fillTriangle`, `fillPolygon` (any polygon), `fillPath` (contours: holes, unions), `fillEllipse`, `strokeEllipse`. Circles = ellipses. Transforms are the caller's: rotated shapes arrive as polygons.[^shapes]

# Edges

Pixel `(x, y)` covers `[x, x+1) × [y, y+1)`; integer coordinates are pixel edges (one-wide stroke on y = 10 straddles rows 9 and 10; on 10.5 fills row 10).

* `HARD`: one sample per row at pixel centres; centre on left/top edge in, right/bottom out. Coverage 255.
* `ANTIALIASED`: 16 sample lines per row, exact horizontal overlap per pixel, summed; coverage 1..255; equal neighbours join.
* Thin hard lines (stroke ≤ 1): one-pixel lines between floored endpoints, every pixel once. Everything else: outlines filled non-zero; butt ends; miter ≤ 4 half-strokes, bevel past it; contours turned one way so pieces unite.
* `strokeRect` above thin = outer minus inner rect, even-odd. Stroked ellipse = ring between `r ± stroke/2`.

# Parity

Arithmetic: IEEE double, `+ − × ÷ sqrt floor ceil`, one fixed order in both drivers; ext built `-ffp-contract=off` (clang otherwise fuses multiply-adds). No trig in Rasterize. Result: identical bytes; hand-worked goldens + seeded `ParityTest` enforce.[^scanner]

Limits: shapes ≤ 2²⁴ (`Rasterizer::LIMIT`), leaving room for derived points under the scanner's 2³⁰ (`Scanner::LIMIT`), where one-pixel line arithmetic still fits 64-bit integers. Clip inside 0..65535.

[^contracts]: `Surface\Contracts\Rasterize`; span format in `Surface\Contracts\Framebuffers\Spans`.
[^shapes]: `Stroker::outline()` is the one stroker; runs in PHP for both drivers.
[^scanner]: `NativeScanner` and ext-rasterize's `core.c` keep the same steps in the same order: change one, change both.
