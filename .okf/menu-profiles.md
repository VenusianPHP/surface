---
type: Architecture
title: Menu-bar profiles
description: >-
  Named engine-neutral menu definitions registered on LiveApplication,
  elected per window, translated by each engine's half of a role table.
tags: [surface, native-windows, menus]
status: draft
generated: { by: claude-opus-5/claude-code, at: "2026-08-30T04:30:00Z" }
revised: { by: claude-opus-5/claude-code, at: "2026-09-13T00:00:00Z", note: "ProgramShuttle replaced by LiveApplication; menu mail is MenuOccurrence on the dock" }
sources:
  - id: spec
    resource: src/Surface/NativeWindows/Menus/MenuItemSpec.php
    title: MenuItemSpec parser
  - id: role
    resource: src/Surface/NativeWindows/Enums/MenuRole.php
    title: MenuRole
  - id: windowable
    resource: src/Surface/NativeWindows/Windowable.php
    title: Windowable election flow
  - id: tests
    resource: tests/NativeWindows/MenuProfileTest.php
    title: Menu profile tests
  - id: mail
    resource: tests/NativeWindows/WindowMailTest.php
    title: Window mail tests
---

# Overview

The two engines disagree about what a menu bar *is* — macOS has one
process-global bar, Linux builds widgets inside each window. Surface's
reconciliation: **profiles**. A sketch registers named definitions once on
`LiveApplication::addMenuBarProfiles()`; a window elects one by name with
`setMenuBar('profile')`; the engine decides what electing means. AppKit
swaps the one real bar (focus-following later); GTK prepends a bar widget
into the window's scaffold.

# The definition is engine-neutral

Parsed once at registration into `MenuItemSpec` trees.[^spec] Engines
receive specs, never sketch arrays. One key decides node kind: `items` =
folder (recursive), `role` = native behaviour, `event` = named SurfaceEvent
the sketch loop drains, `separator`. `label` required except separators;
`id` optional, derived from the label path (`file.quit`) when absent;
`hotkey` is a bare char, the engine adds its platform's primary modifier.

`WINDOW_CLOSED` rides the same dock: each engine's native close path —
`windowShouldClose:` through an `NSWindowDelegate` on macOS (answering
true; close only hides there), `close-request` on GTK (answering false so
GTK destroys; the delegate marks itself closed and guards every later
native call, because GTK recycles the dead handle) — calls
`Windowable::emitWindowClosed()`. The event is named
`window.closed.<window>` so a sketch checks one window with
`has('window.closed.main')` and two windows closing in one tick cannot
collapse. GTK's QUIT role emits it too before destroying, so quitting from
the menu and closing from the chrome look the same to the loop.

Nothing user-authored executes inside a pump. An activated `event` item
pushes a `MenuOccurrence` into the IOPool dock through the shared
`Windowable::emitMenuEvent()`: named `menu.<window>`, the author's event
on `event_name`, plus `id` and `label`. An item whose event is `quit`
pushes `QuitRequested` (named `quit`) instead. Role items push
nothing.[^mail]

The drained bag is ordered and same-name mail stacks — two picks in one
tick are two entries.[^mail] `LiveApplication::events()` re-keys that bag
by `name`, so read that way two picks from one window in one tick collapse
to the last `menu.<window>`. See [async](/async.md).

Vocabulary: `SurfaceEvent` + `SurfaceEventType` in
`Surface\Contracts\Core\Events`; typed mail under
`Surface\Contracts\NativeWindows\Events\{Menu,View,Window}` plus
`QuitRequested`. All implement the framework's `Occurrence`, so the dock
carries them without knowing Surface.

`MenuRole` (string-backed enum)[^role] carries intent — QUIT, ABOUT, HIDE,
CLOSE_WINDOW, MINIMIZE, FULLSCREEN. Each engine owns its half of the
translation table. **A platform with no honest equivalent skips the item
rather than faking it** (GTK has no ABOUT panel, no HIDE, no fullscreen
window action).

ROLE semantics are platform-native by decision: macOS QUIT is `terminate:`
and kills the process from the OS side, GTK QUIT destroys the window.
A sketch that needs portable quit behaviour uses an `event` item instead.

ABOUT is native on both engines and needs identity: `AboutInfo`
(`Surface\Contracts\Core`) registered once via `LiveApplication::setAbout()`
— program-level, like the app menu. The role lands in
`Windowable::showAbout()` → engine `presentAbout()`: AppKit
`orderFrontStandardAboutPanelWithOptions:` (name/version/copyright; the
Credits key wants an attributed string, skipped), GTK a fresh modal
`GtkAboutDialog` transient for the window. No identity registered = the
bare panel, which on an unbundled macOS process says "php". A sketch that
wants its own About uses an `event` item and opens whatever it likes —
another window works today; a portable alert waits on a `GtkAlertDialog`
binding.

# Election flow

`Windowable::setMenuBar(string)` resolves the profile — through the
`live-app` container alias in production, overridable in fakes so the
flow is provable container-free[^windowable] — and hands the spec tree to
the abstract `applyMenuBar()` hook. Unknown profile raises
`WindowableException` at election; malformed definitions raise at
registration, before any engine sees them.[^tests]

# Engine halves

- **AppKit** (`AppKitWindowDelegate::applyMenuBar`): NSMenu tree, role →
  selector (`terminate:`, `performClose:`, …), event items via
  `Bridge::setAction` shared target pushing into the queue, bar held on the
  delegate for the focus-swap slice. Slot 0's rendered title is always the
  process name. Hold minted boxes in variables through the build — the
  temp-chained form lost the bar. Mechanism since pinned and proven: PHP
  frees a temp box the moment `->handle` is read, its destructor releases
  the registry entry, and the ext resolves nil. Rule lives in
  venusian-appkit's AGENTS.
- **GTK** (`GTKWindowDelegate::applyMenuBar`): GMenu model +
  `GtkPopoverMenuBar` prepended into the scaffold — the exact slot the
  scaffold exists for. **KNOWN GAP:** items render but cannot activate.
  Wiring needs `GSimpleActionGroup` + `insertActionGroup`, and jovian/gtk
  has not bound `GSimpleActionGroup`; the only `GActionMap` implementors
  bound are the forbidden `GApplication` family. Until that binding lands,
  items reference nonexistent `win.<id>` actions and render insensitive.
  Separators also deferred (GMenu separators are section boundaries).

# Superseded

The session-level `setMenuBar(array)` — AppKit-dialect arrays with
`selector` / `char_code` leaking through the seam — is removed from the
`BridgedOSSession` contract and `BridgedMacOSSession`. Window-level
election replaces it.

[^spec]: MenuItemSpec parser
[^role]: MenuRole
[^windowable]: Windowable election flow
[^tests]: Menu profile tests
[^mail]: Window mail tests
