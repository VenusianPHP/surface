---
type: Module
title: Windows
description: Window kinds, the toolkit and staged window driver contracts, ToolkitWindowManager, StagedWindowManager.
resource: src/Surface/Windows/
tags: [surface, windows, menus]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-02T19:45:27Z }
sources:
  - id: contracts
    resource: src/Surface/Contracts/Windows/
    title: Window contracts
  - id: manager
    resource: src/Surface/Windows/ToolkitWindowManager.php
    title: ToolkitWindowManager
  - id: staged
    resource: src/Surface/Windows/StagedWindow.php
    title: StagedWindow base
---

# Overview

`OSWindow` = any native window. Two kinds:[^contracts]

* `ToolkitWindow`: owned by a toolkit (AppKit/GTK/Qt) through the bridge; driver `ToolkitWindowDriver`.
* `StagedWindow`: one output filling a native window, no primitives; driver `StagedWindowDriver` (a stager). What a game engine asks of its window.

# ToolkitWindow

`name()`, `title()`, `setTitle()`, `present()` (show + focus), `isOpen()`, `close()`, `setMenuBar(profile)`, `setToggle(item, on)`, `isToggled(item)`, `parent()`, `confirmClose(on)`, `isConfirmingClose()`, `focused()` (the primitive holding keyboard focus, or null), `setInitialFocus(?primitive)` (focused on each `present()`, posting nothing).

* Key precedence while the window is key: a menu accelerator, then the Return/Esc of a default or cancel button, then Tab/Shift-Tab moving focus (unless a focused canvas takes Tab), then the focused native. A key that passes them all posts `WindowKeyPressed`.

* Close: user close button and `close()` take one path: `WindowClosed` once, driver forgets the name, later calls throw `WindowException` ("closed"). After `confirmClose()`, the user's close posts `WindowCloseRequested` and the window stays open; `close()` always closes.
* Child: `open(…, parent: $window)` makes a child, modal to the parent (AppKit sheet, GTK transient modal in the parent's window group, Qt window-modal sheet/dialog). No menu bar; one child per parent; no child of a child (`Surface\Windows\ChildWindows` holds the rules for every driver). Closing the parent closes the child first. On macOS, while a child is key, the default bar shows with every item except the edit roles disabled.
* Button roles: `TKButton::setRole(ButtonRole::DEFAULT|CANCEL|null)`, one holder per role per window. Return/keypad Enter clicks the default, Esc the cancel, wherever the focus is; Return in a focused text area inserts a line; Esc in a child with no cancel button is the user's close.
* Focus: `WindowFocused` on becoming active/key.
* Names unique among open windows; reopening after close allowed.

Primitives ([toolkit primitives](/architecture/primitives.md)): `column|row|grid|fixed(name, …)` declares the one content container (second → `WindowException`); `content()`, `view(path)`, `uuid(uuid)`, `size()` (content area, from the toolkit); `registry()`, `factory()`, `forgetContent($content)` (removed content only) serve primitives and containers. Driver windows `use HostsPrimitives`. Close removes the whole tree; lookups on a closed window throw.

# ToolkitWindowDriver

`open(name, w, h, ?MenuProfile, ?ToolkitWindow parent)`, `has`, `get`, `all`, `closeAll`, `setDefaultMenuBar(?MenuProfile)`. Implemented by each jovian toolkit driver alongside its bridge driver.

Services: `dialogs()`, `clipboard()`, `desktop()`. `Dialogs` (`alert`, `openFiles`, `saveFile`) returns a request id; the answer is [mail](/api/mail.md) carrying it. Drivers extend `Surface\Windows\Services\ToolkitDialogs`, which holds the ids and the parent rules: one modal per parent (a dialog or a child), so a parent with an open dialog refuses a child, another dialog and the user's close; the parent's `close()` answers its open dialog first (`-1` / `[]`); one answer per id. `Dialogs::supports(DialogCapability)`: `AlertTitle` (an `Alert` title) and `ShowHidden` (a request's `showHidden`); `ToolkitDialogs` throws `WindowException` for a request that needs one the driver lacks, before it checks the parent, and a `show*()` that throws leaves no request open. `Alert` also takes `style` (`AlertStyle`), `suppression` (a checkbox label, answered as `AlertAnswered::$suppressed`) and `title`; `OpenRequest` and `SaveRequest` take `filters` (name => glob patterns; not with `folders`) and `showHidden`. `Clipboard`: `setText()`, `hasText()`, `requestText()` (a request id answered by one `ClipboardText`); drivers extend `Surface\Windows\Services\ToolkitClipboard`, which holds the ids, and post `ClipboardChanged` when the content changes. `Desktop::open()` throws `InvalidArgumentException` when nothing is at the path.

Menu bar placement is the driver's: macOS one app-wide bar (active window's bar, else the default bar), Linux a bar inside each window (no default bar shown).

# ToolkitWindowManager

Container `toolkit-windows`. Holds parsed [menu profiles](/api/menu-profiles.md); resolves the default toolkit driver from `toolkit-bridge` and refuses one that does not implement `ToolkitWindowDriver`.[^manager]

* `open(name, w, h, ?profileName)`: sets the configured default bar on the driver, then opens. Drivers treat the same profile instance as a no-op.
* `get`, `all`, `closeAll`, `profile(name)` (unknown → `WindowException`).
* `dialogs()`, `clipboard()`, `desktop()`: the driver's.

# StagedWindow

Base `Surface\Windows\StagedWindow` holds state, validation, capabilities, mail; `HostsDrawing` gives framebuffer, present, lend. Stager supplies native window via `apply*` / `native*` hooks, calls mail hooks from native callbacks.[^staged]

* Open: `StagedWindow::options()` validates `title, visible, resizable, mode, display, display_mode, x, y, borderless, always_on_top, focusable, transparent, confirm_close, vsync`. Stager makes native window with size, resizable, borderless, always_on_top, focusable, transparent, x/y or display applied, then `stage()`: title, vsync, mode, shown. Option needing missing capability (x/y, or a display while not fullscreen, need Position; Exclusive needs ExclusiveFullscreen): native window destroyed, name freed, no mail, throws.
* Mode: `WindowMode` Windowed, Maximized, Minimized, Fullscreen (borderless, optional `Display` to cover), Exclusive (needs `DisplayMode`). `setMode()` skips same mode and target. `exclusiveMode()`. Native `modeChanged()` posts `WindowModeChanged` for asked and user-chosen modes.
* Displays: `Display` (id, name, bounds and usable `Region` in desktop points, scale, current and desktop `DisplayMode`, `?Hdr`). `display()`, `displayModes()`, `moveToDisplay()` (windowed needs Position; fullscreen covers it; exclusive moves by `setMode`). Manager and driver: `displays()`, `primaryDisplay()`.
* Geometry: `position()`, `move()`, `setLimits()` (0 = unbounded), `setAspectRatio()`, `safeArea()` (window points).
* Style: resizable, borderless, always-on-top, focusable, opacity; transparent fixed at open.
* Capabilities: `WindowCapability` Position, AlwaysOnTop, Focusable, Opacity, Icon, KeepAwake, Attention, ExclusiveFullscreen, FrameClock, HitTest. `capabilities()` from stager's `nativeCapabilities()` for its backend (`backend()`: "sdl3/wayland"). Missing → `WindowException` "cannot <doing>: the <backend> backend does not support it". Optional apply hooks default to throwing: a stager overrides those it lists.
* Presentation: `setVsync(VSync)` applies to own present, rides `LentSurface::vsync()` / `onVsync()` to a borrower. `setScaling(ScaleFilter, ScaleFit)`; `presentRect(w, h)` = where a framebuffer lands in window pixels (Stretch, Letterbox, Integer; Integer letterboxes down when larger); scaling change re-presents whole frame.
* Rest: `keepAwake()`, `requestAttention()`, `setIcon(rgba8, w, h)`, `hitTest(?Closure(x, y): HitArea)`, `hdr()`.
* Mail: latest wins `WindowResized`, `WindowMoved`, `WindowFrameDue` (FrameClock stagers); in order `WindowFocused`, `WindowFocusLost`, `WindowModeChanged`, `WindowOccluded` / `WindowExposed` (edge only), `WindowDisplayChanged`, `WindowScaleChanged`, `WindowCloseRequested`, `WindowClosed`; session-level `DisplaysChanged`. All dropped once closed.
* Close: `closeRequested()` (close box, should-close) closes, or with `confirm_close` posts `WindowCloseRequested` and waits for `close()`. `closed()` is the one close path: reclaim lent surface, flush latest, `WindowClosed`, forget.

Lent surface carries window scaling (`scaling()`, `onScaling()`, `presentRect()` via `ScaleFit::rect()`) + live `hdr()`, beside vsync. `setScaling()` forwards. Canvases lend Linear/Stretch, no HDR.

# StagedWindowManager

Container `staged-windows`. `open(name, w, h, options)` through the stager `'toolkit'` names or `bridge.stage.<os>.default`; `get`, `all`, `closeAll`, `displays()`, `primaryDisplay()`, `driver()`. Remembers each name's stager; drops names a stager forgot.

[^contracts]: Window contracts
[^manager]: ToolkitWindowManager
[^staged]: StagedWindow base
