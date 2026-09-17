---
type: Roadmap
title: Components to come — Fonts
description: >-
  One Surface component with a directory reserved and no code. Facts it will
  need, recorded so the work starts from them.
tags: [surface, roadmap, fonts]
status: draft
generated: { by: claude-sonnet/claude-code, at: "2026-09-17T04:37:15Z" }
---

# Fonts

Separate component (tubes lineage). The Painter has no text.

- No rasteriser in the stack (sdl3ttf unprojected, no FreeType binding).
- Port tubes' Adafruit-GFX bitmap format + `FontMakeCommand` → glyph atlas
  → textured-quad batch through `Drawing2D::image()`. Works on every engine
  and on panels.
