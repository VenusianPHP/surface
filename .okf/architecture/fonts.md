---
type: Module
title: Fonts
description: "Two font types: bitmap faces (GFXFont), drawn on the CPU, and TrueType faces through an ext-only FontDriver, drawn by every engine. One registry; text() on a rendering engine draws both."
resource: src/Surface/Fonts/
tags: [surface, fonts, text, drawing]
status: draft
generated: { by: claude-fable/5.1, at: "2026-10-04T00:00:00Z" }
sources:
  - id: contracts
    resource: src/Surface/Contracts/Fonts/
    title: GFXFont, Glyph, FontRegistry, FontException, FontEncoding, YOffsetMode, Font, FontType, TrueTypeFont, FontDriver, ShapedRun, ShapedGlyph, GlyphMask
  - id: extended
    resource: src/Surface/Fonts/Extended/ExtendedFontDriver.php
    title: ExtendedFontDriver over FreeType, HarfBuzz and fontconfig
  - id: velvet
    resource: src/Surface/Drawing/Velvet/VelvetGE.php
    title: Velvet paints the glyphs command as coverage spans
  - id: truetype-test
    resource: tests/Drawing/TrueTypeTextTest.php
    title: Pest coverage for the glyphs command and the path route
  - id: velvet-glyphs-test
    resource: tests/Drawing/VelvetGlyphsTest.php
    title: Pest coverage for Velvet's glyph spans
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

Two types, `Font::type()` → `FontType`:[^contracts]

* **Bitmap** = `GFXFont` subclass: glyph table + bytes, no drawing. Three encodings: Adafruit GFX 1bpp row-major, LVGL 1bpp / 4bpp, classic 5x7 column-major. Drawn on the CPU (GPU engines blur them).
* **TrueType** = `TrueTypeFont(pattern, size, FontDriver)`: a fontconfig pattern at a size in units. `at(size)` = same pattern and driver. Size not finite or ≤ 0 → `FontException::badSize()`. Drawn by every engine; Velvet is the reference GpuParity holds GPU engines to.

`FontDriver`: `shape(pattern, pixelSize, utf8)` → `ShapedRun` (glyphs, advance, ascent, descent, lineHeight) · `mask(face, pixelSize, glyph, style)` → `GlyphMask` (coverage 0..255 rows top-down, `left`/`top` from the origin, `top` up) · `outline(…)` → contours in pixels, y down, flattened to 0.1 px · `close()`. `ShapedGlyph::$face` = `"<path>#<index>"`; `style` bits `OBLIQUE`, `EMBOLDEN`.[^contracts]

The one driver is `ExtendedFontDriver` (`extended`): FreeType, HarfBuzz and fontconfig, called only under `src/Surface/Fonts/Extended/`.[^extended]

```php
$face = app('fonts')->face();     // default slug: classic
$g->text("HELLO\nWORLD", 2, 2, Color::rgb(255, 255, 255), $face);
[$x, $y, $w, $h] = $g->textBounds('HELLO', $face);
```

# Registry

`app('fonts')` = `FontManager` (also `FontRegistry::class`). slug → class, one instance per slug.[^manager][^fonts-test]

* `face(?slug)`, `has()`, `slugs()`, `defaultSlug()`, `extend(slug, class)`. Unknown slug / non-`GFXFont` class → `FontException`.
* `classic` (`ClassicFont`, 5x7) always registered. `config/fonts.php`: `default` (`FONT_FACE`), `faces` = `slug => ['class', 'enabled']` (bitmap) or `slug => ['pattern', 'size', 'enabled']` (TrueType); `truetype(slug, pattern, size)` at run time.
* `new FontManager(config, ?Closure $driver)`: `$driver` is called once, on the first TrueType `face()`; the provider passes `app('truetype')->driver()`. Null, or exts missing → a TrueType `face()` throws `FontException::extensionsMissing()` (names ext-freetype, ext-harfbuzz, ext-fontconfig); bitmap faces work unchanged.
* `php computer make:font Name` → `app/Fonts/Name.php`; `--from=Face.h` imports an Adafruit GFX header (`AdafruitGfxHeader`). The command is bound in the container in `register()`: the console's lazy loader finds a command by its binding.
* No `Fonts::` alias in 0.10: the framework has no magic-alias base.

# Text on an engine

`text(string, x, y, Color, Font)` and `textBounds(string, Font)` are on the `RenderingEngine` contract, written once in the shared base. A `Font` of neither type → `DrawingException::unknownFont()`.[^engine][^text-test]

## Bitmap

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

## TrueType

Same origin and line rules: (x, y) = top-left of the first line box, lines split on `"\n"`, `"\r"` removed. Each line is shaped once; baseline = line top + `ascent`, next line top += `lineHeight`; an empty line advances by the `lineHeight` of `' '`.[^engine][^truetype-test]

| When | Lowered to |
|---|---|
| transform `b = c = 0`, `a = d > 0` | `['glyphs', TrueTypeFont, pixelSize, glyphs, rgba, box, clip]`: pixelSize = size × a; glyphs `[face, glyph, maskLeft, maskTop, style]` in framebuffer pixels; box = union of the masks |
| anything else (turned, uneven scale) | one `path`, non-zero: every glyph's `outline()` at size, at its unrounded origin, through the transform |

No glyph with ink, or an empty clip: no command. `textBounds()` = the mask union at size, origin (0, 0); zeros when nothing inks.

Velvet: one `paintSpans()` per command. Each mask row's runs of equal non-zero coverage, cut to the clip; hard edges keep coverage ≥ 128 at 255. Runs cached per `face|pixelSize|glyph|style|edges`, 2048 entries, least recently used first out.[^velvet][^velvet-glyphs-test]

Measured (Mac, `monospace` 13 px through `ExtendedFontDriver`, 400×340 RGBA8 extended full, warm caches, whole frames): 2400 characters (40 lines × 60, the lower lines cut) 36.6 ms.

# Not here

* Face data beyond classic (the 57 `venusian/letterhead` faces) is not ported to 0.10.
* No native PHP font driver: TrueType needs ext-freetype, ext-harfbuzz and ext-fontconfig.
* Glyph origins are rounded to whole pixels: no subpixel positioning.[^engine]
* Translucent TrueType text blends twice where two glyph boxes overlap.[^velvet]
* No bidi: none of the three libraries does it.

[^contracts]: GFXFont, Glyph, FontRegistry, FontException, FontEncoding, YOffsetMode, Font, FontType, TrueTypeFont, FontDriver, ShapedRun, ShapedGlyph, GlyphMask
[^extended]: ExtendedFontDriver over FreeType, HarfBuzz and fontconfig
[^velvet]: Velvet paints the glyphs command as coverage spans
[^truetype-test]: Pest coverage for the glyphs command and the path route
[^velvet-glyphs-test]: Pest coverage for Velvet's glyph spans
[^manager]: FontManager, the registry
[^typesetter]: Typesetter, layout, bounds and inked runs
[^engine]: text() and textBounds() on every engine
[^text-test]: Pest coverage for text on every driver pairing
[^fonts-test]: Pest coverage for the registry, the classic face, the header importer
