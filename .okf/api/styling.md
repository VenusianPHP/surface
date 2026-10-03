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

* `FontSpec(float $size, FontWeight $weight = REGULAR, ?string $family = null)`: size points, > 0 else `InvalidArgumentException`; family null = system font.
* `FontWeight`: `LIGHT`, `REGULAR`, `MEDIUM`, `SEMIBOLD`, `BOLD`, `BLACK`; `toCssWeight()` → 300/400/500/600/700/900. Drivers map to toolkit scale.
* `TextAlignment`: `LEFT`, `CENTER`, `RIGHT`.

Used by `setFont()` on label, button, text input, text area; `setAlignment()` on label.

# Per toolkit

AppKit: `NSColor`, `NSFont`, layer background. GTK: one `GtkCssProvider` per view, class `tk-<uuid>`. Qt: `setStyleSheet` per widget, `QFont`.

[^color]: Color
[^styling]: Typography values
