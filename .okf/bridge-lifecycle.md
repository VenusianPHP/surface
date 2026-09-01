---
type: Architecture
title: OS bridge lifecycle
description: >-
  How Surface opens, ticks, and closes the link between PHPland and one OS
  windowing engine, and why engine start and session connection are two
  separate things.
tags: [surface, bridge, lifecycle, native-windows]
status: draft
generated: { by: claude-opus-5/cursor, at: "2026-08-29T21:00:00Z" }
revised: { by: claude-opus-5/claude-code, at: "2026-08-30T02:30:00Z", note: "fifth verb added; back to draft pending re-verification" }
sources:
  - id: contract
    resource: src/Surface/Contracts/Bridge/BridgedOSSession.php
    title: BridgedOSSession contract
  - id: abstract
    resource: src/Surface/Bridge/BridgedOSSession.php
    title: Abstract session with the shared state machine
  - id: manager
    resource: src/Surface/Bridge/BridgeManager.php
    title: BridgeManager
  - id: tests
    resource: tests/Bridge/SessionTest.php
    title: Session lifecycle tests
---

# Overview

A sketch is a long-running process, so the bridge to the OS is a singleton
held for the life of it. `OSAppBridge::connect()` hands back a live session
and the sketch loop pumps it.[^manager]

```text
OSAppBridge (MagicAlias, accessor 'os-bridge')
  └── Surface\Bridge\BridgeManager           (singleton, memoises the session)
        └── Actions\Build{Mac,Linux}OSSession
              └── vessel->get('mac.bridge' | 'linux.bridge')
                    └── the engine package's session ->connect()
```

# The contract

Five verbs on `Surface\Contracts\Bridge\BridgedOSSession`:[^contract]

| Method | Meaning |
|---|---|
| `connect(): static` | Bring the session up, starting the engine first if it has never run. |
| `disconnect(): void` | Take the session down, draining anything still queued. |
| `connected(): bool` | Whether the session is currently up. |
| `pump(int $budget_ms = 0): int` | Advance the engine's loop; answers units of work dispatched. |
| `provisionNewWindow(string $name, int $w, int $h): OSWindow` | Mint a native window and wrap it in a `Windowable`. |

The first four are lifecycle and live in the abstract. The fifth is a
factory and lives only in the engine packages: the session is the sole
engine-aware object Surface can reach, so it is where minting happens. It
carries no shared policy and no connection guard. See
[window-provisioning](/window-provisioning.md).

The budget is **integer milliseconds** because that is GTK's native unit;
the AppKit session divides to the float seconds its bridge wants. The
contract states that a pump **may block for its whole budget**, because
GTK's iteration genuinely does when its context is empty. AppKit conforms
to the slower promise rather than the abstraction pretending both are
instant.

# Two flags, two different guarantees

`Surface\Bridge\BridgedOSSession` owns the whole state machine so engine
packages inherit every guard.[^abstract]

- **`$initialized`** guards the once-per-process engine start. AppKit's
  `finishLaunching()` and GTK's `gtk_init` cannot be repeated and cannot be
  undone. `bootstrap()` runs it at construction, so a freshly constructed
  session is initialised but **not** connected.
- **`$connected`** guards the part that cycles freely. Connect and
  disconnect may alternate for the life of the process.

Splitting them is what makes `disconnect()` mean something real instead of
being bookkeeping. See [engine-asymmetries](/engine-asymmetries.md) for what
connection actually does on each OS.

# Hooks an engine package supplies

| Hook | Called |
|---|---|
| `initializeEngine()` | Once, from `bootstrap()` at construction. |
| `connectToEngine()` | Every `connect()` on a disconnected session. |
| `disconnectEngine()` | Every `disconnect()` on a connected session. |
| `pumpEngine(int $budget_ms)` | Every `pump()` while connected. |

# Invariants

Proven by fake-driven tests with no engine present.[^tests]

- Construction initialises exactly once and leaves the session disconnected.
- `connect()` and `disconnect()` are both idempotent.
- `disconnect()` drains with `pump(0)` **before** calling `disconnectEngine()`.
- A full disconnect/reconnect cycle still shows exactly one `initializeEngine()`.
- `pump()` answers `0` and touches nothing while disconnected. It does not
  throw, so a sketch loop outliving its bridge cannot blow up mid-tick.

# Teardown

There is no engine un-init on either platform, and both runtimes already
refuse to release handles during interpreter shutdown — releasing an
`NSWindow` after `NSApp` teardown crashes, and GTK recycles handles. So
process exit is the real teardown. Surface's job is to stop touching handles
once it has said it is done, not to unwind the engine.

[^contract]: BridgedOSSession contract
[^abstract]: Abstract session with the shared state machine
[^manager]: BridgeManager
[^tests]: Session lifecycle tests
