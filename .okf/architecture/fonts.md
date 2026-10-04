---
type: Module
title: Fonts
description: "Bitmap faces (GFXFont) and their registry; text() on a rendering engine draws them onto any framebuffer, no window."
resource: src/Surface/Fonts/
tags: [surface, fonts, text, drawing]
status: draft
generated: { by: claude-fable/5.1, at: "2026-10-04T00:00:00Z" }
sources:
  - id: contracts
    resource: src/Surface/Contracts/Fonts/
    title: GFXFont, Glyph, FontRegistry, FontException, FontEncoding, YOffsetMode
  - id: manager
    resource: src/Surface/Fonts/FontManager.php
    title: FontManager, the registry
  - id: typesetter
    resource: src/Surface/Drawing/Text/Typesetter.php
    title: Typesetter, layout, bounds and inked runs
  - id: engine
    resource: src/Surface/Drawing/RenderingEngine.php
    title: text() and textBounds() on every engine
  - id: text-test
    resource: tests/Drawing/TextTest.php
    title: Pest coverage for text on every driver pairing
  - id: fonts-test
    resource: tests/Fonts/
    title: Pest coverage for the registry, the classic face, the header importer
---

# Overview

Face = `GFXFont` subclass: glyph table + bytes, no drawing. Three encodings: Adafruit GFX 1bpp row-major, LVGL 1bpp / 4bpp, classic 5x7 column-major.[^contracts]

```php
$face = app('fonts')->face();     // default slug: classic
$g->text("HELLO\nWORLD", 2, 2, Color::rgb(255, 255, 255), $face);
[$x, $y, $w, $h] = $g->textBounds('HELLO', $face);
```

# Registry

`app('fonts')` = `FontManager` (also `FontRegistry::class`). slug → class, one instance per slug.[^manager][^fonts-test]

* `face(?slug)`, `has()`, `slugs()`, `defaultSlug()`, `extend(slug, class)`. Unknown slug / non-`GFXFont` class → `FontException`.
* `classic` (`ClassicFont`, 5x7) always registered. `config/fonts.php`: `default` (`FONT_FACE`), `faces` = `slug => ['class', 'enabled']`.
* `php computer make:font Name` → `app/Fonts/Name.php`; `--from=Face.h` imports an Adafruit GFX header (`AdafruitGfxHeader`). The command is bound in the container in `register()`: the console's lazy loader finds a command by its binding.
* No `Fonts::` alias in 0.10: the framework has no magic-alias base.

# Text on an engine

`text(string, x, y, Color, GFXFont)` and `textBounds(string, GFXFont)` are on the `RenderingEngine` contract, written once in the shared base.[^engine][^text-test]

* (x, y) = top-left of the first line box. `"\n"` = new line (face's line height), `"\r"` ignored, codes outside the face skipped.
* One glyph pixel = one unit. Scale / turn / clip through the engine's state, as for shapes.
* 4bpp faces ink where a nibble ≥ the face's `alpha_threshold`.
* `Typesetter`: `layout()` → placed glyphs, `bounds()` → ink box, `runs()` → `[row, first, last]` inclusive, cached per face class + glyph.[^typesetter]

Two routes, same pixels (`it gives the same pixels whether the text lands on whole pixels or goes through the rasteriser`):

| When | Lowered to | Cost |
|---|---|---|
| upright, whole-number scale ≥ 1, origin on whole pixels, opaque colour | `['spans', bytes, rgba, clip]`: spans written in PHP, clipped there, one `paintSpans()` | no rasterising |
| anything else (turned, fractional, translucent) | one `['path', …]`: every run a rectangle of one path | one `fillPath()` |

Translucent text takes the path route so overlapping glyph boxes blend once.

Measured (Mac, classic face, 400×340 RGBA8): 40 characters 0.4 ms extended / 1.5 ms native; 2400 characters 24 ms extended / 72 ms native. The path route for the same 2400: 246 ms extended / 2.1 s native.

# Not here

Face data beyond classic (the 57 `venusian/letterhead` faces) is not ported to 0.10.

[^contracts]: GFXFont, Glyph, FontRegistry, FontException, FontEncoding, YOffsetMode
[^manager]: FontManager, the registry
[^typesetter]: Typesetter, layout, bounds and inked runs
[^engine]: text() and textBounds() on every engine
[^text-test]: Pest coverage for text on every driver pairing
[^fonts-test]: Pest coverage for the registry, the classic face, the header importer
