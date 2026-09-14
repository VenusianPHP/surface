---
type: Architecture
title: The loop and the IOPool dock
description: >-
  How a sketch's loop rides the framework's IOPool dock: LiveApplication aims
  the os resource's wait budget and pumps every resource, windows push typed
  mail, HTTP is the dock's http resource.
tags: [surface, async, http, tick, io-pools]
status: draft
generated: { by: claude-opus-5/claude-code, at: "2026-08-30T22:30:00Z" }
revised: { by: claude-opus-5/claude-code, at: "2026-09-13T00:00:00Z", note: "ProgramShuttle replaced by LiveApplication; rewritten around the IOPool dock" }
sources:
  - id: app
    resource: src/Surface/Core/LiveApplication.php
    title: LiveApplication — tick, events
  - id: os
    resource: src/Surface/Core/IOPools/OSLevelResourceDriver.php
    title: OSLevelResourceDriver — the dock's os resource
  - id: provider
    resource: src/Surface/Core/Providers/SurfaceServiceProvider.php
    title: SurfaceServiceProvider — registers the os resource at boot
  - id: dock
    resource: venusian/framework:src/Voyager/IOPools/IOPoolDock.php
    title: IOPoolDock
  - id: http
    resource: venusian/framework:src/Voyager/IOPools/Drivers/MultiCurlResourceDriver.php
    title: MultiCurlResourceDriver — the http resource
  - id: tests
    resource: tests/Core/LiveApplicationTest.php
    title: LiveApplication tests
---

# The loop

The sketch owns the loop. Each turn:

```php
$app = LiveApp::get();      // accessor 'live-app'
$app->tick(16);             // idle budget, integer ms
$events = $app->events();   // IOEventBag keyed by name
```

`tick($ms)` aims the `os` resource's wait budget, then `pump()`s the dock,
which ticks every registered resource.[^app] The budget is idle time, not
latency: the native pump blocks in the OS wait and wakes instantly on
input — never a blind sleep.[^os]

| Piece | Owner | Job |
|---|---|---|
| `IOPoolDock` | framework `voyager/io-pools` | named resources + the mail bag; `push()`, `pump()`, `drain()`[^dock] |
| `OSLevelResourceDriver` | Surface, registered as `os` | pumps the session with the budget, then `syncLayout()` on every window[^os] |
| `http` resource | framework, `resources.http.enabled` in `config/io-pools.php` | `MultiCurlResourceDriver`, named non-blocking calls[^http] |
| `Windowable` | Surface | handed the dock at provisioning; pushes typed mail for views, menus, close, resize |

`SurfaceServiceProvider::boot()` builds `OSLevelResourceDriver` and
registers it on the dock as `os`.[^provider] Anything else periodic rides
the same loop: `$dock->resource($name, $driver)` with an `IOResourceDriver`
whose `tick()` returns fast. `LiveApplicationTest` proves a second resource
ticks alongside `os` on every turn.[^tests]

# Mail

The dock's bag is an ordered list of `QueuedIO`; `drain()` hands it over
and starts a fresh one. `LiveApplication::events()` re-keys the drained
bag by `name`, so keyed that way same-name entries from one tick collapse
to the last. Surface's vocabulary and its names are in
[views](/views.md) and [menu-profiles](/menu-profiles.md).

# HTTP

`fetch($name, $url, $headers, $params)`, `post(...)` and
`call($name, $url, $method, $headers, $body)` on the `http` resource each
answer a `Presumption` carrying `onSuccess` / `onFail` / `onProgress`
hooks.[^http]

- One in-flight call per name. A duplicate throws `IOPoolsException`; the
  name frees when the call settles.
- On completion the `HttpResult` is pushed into the dock under the call's
  name **and** settles the Presumption — both lanes, always. An optional
  `envelope` callable reshapes what is pushed; the Presumption still gets
  the raw result.
- `ok` is transport truth (`CURLE_OK`). A 404 is `ok` with status 404; the
  sketch judges.
- Download progress is observed each tick and forwarded to the
  Presumption when it moves.

# Decisions

- **No fork after `connect()`** — NSApplication and GTK are fork-unsafe
  post-init. Kernel multiplexing is the parallelism, so nobody needs to.
- **The machinery lives in the framework** so headless sketches get it
  without windowing. Surface contributes the `os` resource and its mail
  vocabulary, nothing else.

[^app]: LiveApplication — tick, events
[^os]: OSLevelResourceDriver — the dock's os resource
[^provider]: SurfaceServiceProvider — registers the os resource at boot
[^dock]: IOPoolDock
[^http]: MultiCurlResourceDriver — the http resource
[^tests]: LiveApplication tests
