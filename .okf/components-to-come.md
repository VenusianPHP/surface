---
type: Roadmap
title: Components to come — HumanInput, Fonts
description: >-
  Two Surface components with directories reserved and no code. Facts each
  will need, recorded so the work starts from them.
tags: [surface, roadmap, input, fonts]
status: draft
generated: { by: claude-opus-5/claude-code, at: "2026-09-14T00:00:00Z" }
---

# HumanInput

Separate component (tubes lineage). Stages are draw-only until it lands.

- AppKit gives PHP no key/mouse events: `Bridge::pump` dispatches inside C;
  NSEvent monitors are blocks (reserved). Needs an ext-appkit Bridge tap.
- SDL3: key, text, motion, button, wheel payloads readable today via
  `SDLReadEvent`; gamepad payloads are not. SDL stage session currently
  frees input events unread.
- Starting shapes: tubes' `Keyboard`, `Mouse`, `GamePad`, `AnalogStick`,
  `DigitalButton`.

# Fonts

Separate component (tubes lineage). The Painter has no text.

- No rasteriser in the stack (sdl3ttf unprojected, no FreeType binding).
- Port tubes' Adafruit-GFX bitmap format + `FontMakeCommand` → glyph atlas
  → textured-quad batch through `Drawing2D::image()`. Works on every engine
  and on panels.
