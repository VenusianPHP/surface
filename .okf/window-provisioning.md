---
type: Architecture
title: Window provisioning
description: >-
  How a sketch gets a native window: the session mints it, a per-OS driver
  registers it, and ProgramShuttle is the only place the two meet.
tags: [surface, native-windows, windows, lifecycle]
status: draft
generated: { by: claude-opus-5/claude-code, at: "2026-08-30T02:30:00Z" }
sources:
  - id: shuttle
    resource: src/Surface/Core/ProgramShuttle.php
    title: ProgramShuttle
  - id: driver
    resource: src/Surface/NativeWindows/Drivers/NativeWindowDriver.php
    title: NativeWindowDriver, the shared registry
  - id: windowable
    resource: src/Surface/NativeWindows/Windowable.php
    title: Windowable
  - id: manager
    resource: src/Surface/NativeWindows/WindowManager.php
    title: WindowManager
  - id: tests
    resource: tests/Core/ProgramShuttleTest.php
    title: ProgramShuttle tests
---

# Overview

The layer above [bridge-lifecycle](/bridge-lifecycle.md). A sketch holds one
`ProgramShuttle`, which pairs one connected session with one window
driver.[^shuttle]

```text
Program (MagicAlias, accessor 'os-program')
  └── Surface\Core\ProgramShuttle          (singleton: session + driver)
        ├── BridgedOSSession->provisionNewWindow()   mints the delegate
        └── OSWindowDriver->add() / presentWindow()  registers and shows it
```

Minting and holding are split. The **session** is the only engine-aware
object Surface can reach, so it is the factory. The **driver** is a
name-keyed registry with no engine knowledge, so its policy is provable
with fakes.

# The two halves

| | Owner | Job |
|---|---|---|
| Mint | engine session, `provisionNewWindow(name, w, h)` | build the native window, wrap it in a `Windowable` |
| Hold | `NativeWindowDriver` subclass | key by `name()`, guard presentation, tear down |

`Windowable` supplies what every delegate shares: the registered `name()`
and the `title()` combo that reads with no argument and writes with
one.[^windowable] Engine packages fill in `present()`, `destroy()`,
`isPresenting()`, `getTitle()`, `setTitle()`.

Drivers key the registry with `OSWindow::name()`, never a public field.
`Windowable` also exposes a public readonly `$name`, so property access
appears to work against every shipped delegate and fails only on a
contract-only implementation. `tests/NativeWindows/DriverContractTest.php`
holds that line.

# Invariants

Proven with a fake session and a fake driver, no engine present.[^tests][^driver]

- `provisionWindow()` answers `false` and mints nothing when the name is
  taken. It never replaces a live window behind the caller's back.
- `presentWindow()` raises `WindowableException` for an unknown name, and
  will not re-present a window already on screen.
- `destroyAll()` destroys every window and empties the registry, so a
  driver can be refilled after teardown.
- `destroy()` tears the windows down **before** draining, so the engine sees
  the closes, then disconnects. It is safe on an already-disconnected
  session and safe to call twice.
- `provisionNewWindow()` is not gated on connection. The abstract session
  guards the loop, not the factory, so a disconnected session still reaches
  the engine.

# Driver selection

`BridgeManager` picks the session from `device_os_family()`. `WindowManager`
picks the driver from `config('windows.default')`, which defaults to the
same call but is overridable with `WINDOW_DRIVER`.[^manager] The two lookups
are independent, so the env override on the other platform makes the session
mint a delegate its driver rejects.

Marker interfaces make that a typed failure rather than a native crash:
`AppKitWindowDriver` accepts only a `MacOSWindow`, `GTKWindowDriver` only a
`LinuxOSWindow`, and a mismatch raises `WindowableException` at `add()`.

# Open

- **Whether the driver should follow the session** rather than resolving the
  OS a second time.
- **Last-window-closed has no meaning yet.** Nothing maps it to a sketch
  stop, and the driver has no count or emptiness query. The two platforms
  need different reads: on macOS a closed window is hidden
  (`setReleasedWhenClosed(false)`) so `isPresenting()` answers honestly; on
  GTK close destroys the widget, so the same poll would touch a dead handle.
- **Placement.** The macOS delegate is minted at `NSRect(0, 0, w, h)`, the
  screen's bottom-left under AppKit's origin. `center()` is on `MacOSWindow`
  only, so a portable sketch cannot reach it, and GTK4 has no positioning at
  all. See [engine-asymmetries](/engine-asymmetries.md).

[^shuttle]: ProgramShuttle
[^driver]: NativeWindowDriver, the shared registry
[^windowable]: Windowable
[^manager]: WindowManager
[^tests]: ProgramShuttle tests
