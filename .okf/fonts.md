---
type: Architecture
title: Bitmap fonts — faces, registry, text on both engines
description: >-
  GFXFont faces from a registry; Drawing2D::text() on the Painter (glyph
  atlas, textured quads) and the Rasterizer (runs as spans); one Typesetter
  places glyphs on both; make:font imports Adafruit headers.
tags: [surface, fonts, text, drawing, registry]
status: draft
generated: { by: claude-fable-5.1/claude-code, at: "2026-09-17T00:00:00Z" }
sources:
  - id: contracts
    resource: src/Surface/Contracts/Fonts
    title: Font contracts
  - id: fonts
    resource: src/Surface/Fonts
    title: Registry, ClassicFont, tooling
  - id: text
    resource: src/Surface/Drawing/Text
    title: Typesetter, GlyphAtlas
  - id: painter
    resource: src/Surface/Drawing/Painter.php
    title: Painter::text
  - id: rasterizer
    resource: src/Surface/Drawing/Rasterizer.php
    title: Rasterizer::text
  - id: spec
    resource: docs/superpowers/specs/2026-09-17-bitmap-fonts-design.md
    title: Design
---

# Overview

```php
$hud = Fonts::face('helvb-12');                 // registry, once, outside the hook
$g->text('HELLO', 0.0, 0.0, Color::hex('#fff'), $hud);
[$x, $y, $w, $h] = $g->textBounds('HELLO', $hud);
$g->push()->scale(2.0, 2.0)->text('BIG', 0.0, 16.0, $white, $hud)->pop();
```

A face is a value the sketch holds; drawing never touches the container.
`Fonts` (`'fonts'`) is the registry; `classic` (Adafruit 5x7) is always
there and is the default. `config/fonts.php`: `default`, `faces`.
`venusian/letterhead` adds 57 faces at boot.[^fonts]

# Vocabulary

| Type | Role |
|---|---|
| `GFXFont` | abstract face: `$first $last $y_advance $bitmaps $glyphs`, `$column_major`, `$bits_per_pixel`, `$encoding`, `$y_offset_mode`, `$alpha_threshold`; readers `glyph(code): ?Glyph`, `byte()`, `capHeight()`, `encoding()`, `yOffsetMode()`, `hasBitmapData()` |
| `Glyph` | readonly `bitmap_offset width height x_advance x_offset y_offset` |
| `FontEncoding` | `ADAFRUIT` (glyph i = code first+i) / `LVGL` (reserved glyph 0; 1bpp or 4bpp) |
| `YOffsetMode` | `RAW` (baseline / usable as is) / `LINE` (from the line bottom) |
| `FontRegistry` | `extend`, `face`, `has`, `slugs`, `defaultSlug` — `FontManager` implements it |
| `Typesetter` | engine-free: `layout()` → `PlacedGlyph`s, `bounds()`, `runs()`, `coverage()`, `ascent()`, `top()` |
| `GlyphAtlas` | one RGBA8 texture per face: white, alpha = coverage, shelf-packed |

# Decisions

- **Text is a `Drawing2D` verb.** `text()` and `textBounds()` on the
  contract; Painter and Rasterizer each implement it. Jovian packages
  implement `Executor`, so they never moved.[^spec]
- **`y` is the top of the line box** for every encoding. The Typesetter's
  `top()` folds the three 0.7 `drawChar` rules into one number per glyph;
  `ascent()` is `max(-y_offset)` (Adafruit), `-min(top)` after the
  cap-height nudge (LVGL RAW), 0 (LVGL LINE, classic).[^text]
- **Painter: atlas + quads.** First `text()` with a face bakes a
  `GlyphAtlas` at `max_texture_size`, mints one texture, keeps it for the
  Painter's life (`releaseAtlases()` frees). Six vertices per glyph, tinted
  by the vertex colour — every engine shader multiplies colour × texel.
  4bpp faces blend where the engine blends. One batch per string.[^painter]
- **Rasterizer: runs → spans.** Each glyph row's runs become
  `setSegment` calls (or `RastersNatively::fillRect`); rotate / scale go
  through the polygon fill. 4bpp thresholds at `alphaThreshold()`; CPU
  stays opaque. Never a call per pixel.[^rasterizer]
- **Scale through the stack.** No size parameter. Engines sample `LINEAR`,
  so GPU-scaled text is smooth; CPU-scaled is blocky.
- **`surface/drawing` and `surface/fonts` are peers** over
  `surface/contracts`. Drawing owns `Text\`; Fonts owns the registry,
  `ClassicFont`, `make:font`, the alias. Fonts requires
  `venusian-voyager/console` + `filesystem` for the command.
- **Faces are data.** Properties only; method API free to change. Two
  upstream quirks preserved (FreeMono9Pt 0x7D/0x7E past the table,
  TomThumb 204 entries) — `byte()` answers 0 past the end.

# Tooling

`php computer make:font Name` scaffolds `app/Fonts/Name.php` (empty:
`hasBitmapData() === false`, draws nothing). `--from=path.h` imports an
Adafruit GFXfont header through `AdafruitGfxHeader::parse()` →
`renderClassSource()`. Register the class in `config/fonts.php` `faces`.

# Not in this slice

Kerning, wrapping, alignment, a cursor API, background fills, UTF-8 beyond a
face's range, vector text, nearest-sampled GPU scaling, BDF/LVGL converters,
`RastersNatively` glyph blits.

[^contracts]: Font contracts
[^fonts]: Registry, ClassicFont, tooling
[^text]: Typesetter, GlyphAtlas
[^painter]: Painter::text
[^rasterizer]: Rasterizer::text
[^spec]: Design
