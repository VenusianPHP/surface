---
type: Roadmap
title: Components to come — Fonts
description: >-
  One Surface component with a directory reserved and no code. Facts it will
  need, recorded so the work starts from them.
tags: [surface, roadmap, fonts]
status: draft
generated: { by: cursor-grok-4.6/cursor, at: "2026-09-18T06:20:00Z" }
---

# Fonts

Contracts, `ClassicFont`, `make:font`, engine-free layout
(`Typesetter`, `PlacedGlyph`, `GlyphAtlas`), and `Drawing2D::text()` /
`textBounds()` on both drawers are in.

- No rasteriser in the stack (sdl3ttf unprojected, no FreeType binding).
- Next: letterhead faces (`venusian/letterhead`).
