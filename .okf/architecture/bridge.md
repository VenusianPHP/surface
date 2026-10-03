---
type: Module
title: Bridge
description: ToolkitManager picks a toolkit driver; its session starts the engine once, connects on demand, joins the loop as its sleeper.
resource: src/Surface/Bridge/
tags: [surface, bridge, io-pools]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-02T19:45:27Z }
sources:
  - id: manager
    resource: src/Surface/Bridge/ToolkitManager.php
    title: ToolkitManager
  - id: session
    resource: src/Surface/Bridge/BridgedToolkitSession.php
    title: BridgedToolkitSession
  - id: pump
    resource: src/Surface/Bridge/ToolkitPump.php
    title: ToolkitPump
---

# Overview

Bridge = PHP app ↔ native toolkit (AppKit, GTK, Qt) on the main thread. Toolkit choice is config; OS only picks the default. GTK and Qt run on macOS too.

# ToolkitManager

`Voyager\NutsAndBolts\Manager`. `driver()` → `bridge.toolkit.<device_os_family()>.default` (fallback `gtk`). `driver('gtk'|'qt'|'appkit')` explicit.[^manager]

Refuses before building a driver (`BridgeException`):

* driver package missing → message names `composer require jovian/venusian-<toolkit>`;
* extension not loaded in this PHP → names `ext-<toolkit>` and `PHP_BINARY`;
* `appkit` on Linux.

Drivers extend `ToolkitBridgeDriver(TheServiceContainer $app)`; `connect()` builds the session once, caches it, returns it connected. Drivers read config and other services through `$this->app`, not global helpers.

# Session lifecycle

`BridgedToolkitSession` (contract: `connect`, `disconnect`, `connected`).[^session]

| Step | Hook | Rule |
|---|---|---|
| construct | `initializeEngine()` | once per process, never undone |
| `connect()` | `connectToEngine()` | present to the OS; cyclable |
| `disconnect()` | `disconnectEngine()` | inverse of connect |
| `pump($budget_ns)` | abstract | wait ≤ budget in the toolkit's own wait, dispatch; `0` = dispatch ready only |

Recommended: connect on demand in a sketch's `boot()`. Connecting in `AppServiceProvider` starts the engine for every `computer` command (Dock icon on macOS).

# Mail

Native callbacks only `post($mail)` or `postLatest($key, $mail)`. Before `joinLoop()` mail waits in an outbox (windows can be opened and pumped by hand); after, it goes to `Loop::post()`, and the loop's MailHandler delivers it (sketch `loop($mail)` or Signals). API calls are plain synchronous calls; side effects arrive as mail. See [mail](/api/mail.md), [view mail](/api/view-mail.md).

`postLatest($key, $mail)`: holds mail under `$key`, a later post under the same key replaces it. `ToolkitPump` calls `flushLatest()` after every `pump()` (sleep and tick): pending mail goes out in first-seen key order, so a burst inside one wait (edge-drag resizes) becomes one mail per key with the last value. Keys: `window.resized.<window>`, `view.resized.<window>.<path>`. `forgetLatest($key)` drops a pending key when its target goes (view unwatched/removed, window closing). Latest mail reaches the outbox only through `ToolkitPump`, `joinLoop()` or an explicit `flushLatest()`: a hand-pumping caller (tests) pumps through `ToolkitPump::sleep()`.

# Joining the loop

`joinLoop(Loop $loop)`:[^session][^pump]

1. requires connected; requires `$loop->descriptor()` non-null (kqueue on macOS, epoll on Linux) → else `BridgeException` naming ext-kqueue / ext-epoll and `io-pools.pool_waiters.default`;
2. `wakeDescriptor($fd)`: toolkit-specific; the loop waiter's fd readable ends the toolkit's wait;
3. registers `ToolkitPump` as resource `bridge.toolkit` and `crown()`s it: the loop sleeps inside the toolkit (`sleep($budget)` → `pump($budget)`, `tick()` → `pump(0)`, each then `flushLatest()`);
4. flushes the outbox into the loop, then the pending latest mail.

`leaveLoop()`: forget the resource, `releaseWakeDescriptor()`.

Wake sources are one-shot, re-armed at the start of each `pump()`: a descriptor the loop has not drained yet cannot keep the toolkit's dispatch busy.

[^manager]: ToolkitManager
[^session]: BridgedToolkitSession
[^pump]: ToolkitPump
