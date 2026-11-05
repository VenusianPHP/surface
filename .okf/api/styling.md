---
type: Reference
title: Styling
description: Color (nuts-and-bolts) and the Windows typography values FontSpec, FontWeight, TextAlignment.
resource: src/Surface/Contracts/Windows/Styling/
tags: [surface, styling, color, fonts]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-02T19:45:27Z }
sources:
  - id: color
    resource: src/Surface/NutsAndBolts/Color.php
    title: Color
  - id: styling
    resource: src/Surface/Contracts/Windows/Styling/
    title: Typography values
---

# Color

`Surface\NutsAndBolts\Color` (split `venusian-surface/nuts-and-bolts`), readonly `(red, green, blue, alpha = 1.0)`, each 0..1, else `InvalidArgumentException`. Shared: Windows styles with it, Drawing may later.[^color]

| Call | Result |
|---|---|
| `Color::rgb(255, 0, 128)` | opaque from 0..255 |
| `Color::rgba(0, 0, 0, 0.5)` | 0..255 + 0..1 alpha |
| `Color::hex('#f08')`, `'#ff0080'`, `'#ff008080'` | `#` optional; other forms throw |
| `->toCss()` | `rgba(255, 0, 128, 1)` (CSS, Qt style sheets) |
| `->toHex()` | `#rrggbb`, `#rrggbbaa` when alpha < 1 |

Used by `setBackground(?Color)` and `setTextColor(?Color)`; null restores toolkit's own.

# Typography

`Surface\Contracts\Windows\Styling`. Windows only by rule, never Drawing.[^styling]

* `FontSpec(float $size, FontWeight $weight = REGULAR, ?string $family = null, bool $monospace = false, bool $italic = false)`: size points, > 0 else `InvalidArgumentException`; family null = system font; `monospace` with no family = the OS's fixed-pitch face, a named family wins; `italic` = the family's italic or oblique face.
* `FontMetrics(float $ascent, float $descent, float $lineHeight, float $averageCharWidth, bool $fixedPitch)`: what `ToolkitWindowDriver::fontMetrics(FontSpec)` measured, in points; descent positive; a negative or non-finite size throws `InvalidArgumentException`.
* `TextRangeStyle(int $line, int $start, int $length, ?Color $foreground = null, ?Color $background = null, bool $bold = false, bool $italic = false, bool $underline = false, bool $strikeout = false)`: line, start and length in code points of that line; needs a colour or a flag (`hasFlags()`); negatives throw `InvalidArgumentException`. A clipped run keeps its flags.
* `ScrollPosition(float $x, float $y, float $width, float $height)`: the visible rect as content fractions 0..1; `of()` builds it from a toolkit's offsets and sizes, `offset()` turns a fraction back into an offset.
* `FontWeight`: `LIGHT`, `REGULAR`, `MEDIUM`, `SEMIBOLD`, `BOLD`, `BLACK`; `toCssWeight()` → 300/400/500/600/700/900. Drivers map to toolkit scale.
* `TextAlignment`: `LEFT`, `CENTER`, `RIGHT`.

Used by `setFont()` on label, link, button, text input, text area; `setAlignment()` on label, link, button (centre until set), text input and text area (left until set). A text area's `setTabWidth(int $columns)` (≥ 1, 8 until set) puts stops every columns × the font's space advance.

# Per toolkit

AppKit: `NSColor`, `NSFont`, layer background. GTK: one `GtkCssProvider` per view, class `tk-<uuid>`. Qt: `setStyleSheet` per widget, `QFont`.

[^color]: Color
[^styling]: Typography values
