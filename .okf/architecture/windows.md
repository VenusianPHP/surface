---
type: Module
title: Windows
description: Window kinds, the toolkit window driver contract, ToolkitWindowManager.
resource: src/Surface/Windows/
tags: [surface, windows, menus]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-01T20:04:54Z }
sources:
  - id: contracts
    resource: src/Surface/Contracts/Windows/
    title: Window contracts
  - id: manager
    resource: src/Surface/Windows/ToolkitWindowManager.php
    title: ToolkitWindowManager
---

# Overview

`OSWindow` = any native window. Two kinds:[^contracts]

* `ToolkitWindow`: owned by a toolkit (AppKit/GTK/Qt) through the bridge; driver `ToolkitWindowDriver`.
* `StagedWindow`: Surface-drawn stage window; driver `StagedWindowDriver`. Contracts only this line.

# ToolkitWindow

`name()`, `title()`, `setTitle()`, `present()` (show + focus), `isOpen()`, `close()`, `setMenuBar(profile)`, `setToggle(item, on)`, `isToggled(item)`.

* Close: notify-after, no veto. User close button and `close()` take one path: `WindowClosed` once, driver forgets the name, later calls throw `WindowException` ("closed").
* Focus: `WindowFocused` on becoming active/key.
* Names unique among open windows; reopening after close allowed.

# ToolkitWindowDriver

`open(name, w, h, ?MenuProfile)`, `has`, `get`, `all`, `closeAll`, `setDefaultMenuBar(?MenuProfile)`. Implemented by each jovian toolkit driver alongside its bridge driver.

Menu bar placement is the driver's: macOS one app-wide bar (active window's bar, else the default bar), Linux a bar inside each window (no default bar shown).

# ToolkitWindowManager

Container `toolkit-windows`. Holds parsed [menu profiles](/api/menu-profiles.md); resolves the default toolkit driver from `toolkit-bridge` and refuses one that does not implement `ToolkitWindowDriver`.[^manager]

* `open(name, w, h, ?profileName)`: sets the configured default bar on the driver, then opens. Drivers treat the same profile instance as a no-op.
* `get`, `all`, `closeAll`, `profile(name)` (unknown → `WindowException`).

[^contracts]: Window contracts
[^manager]: ToolkitWindowManager
