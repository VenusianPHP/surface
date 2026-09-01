---
type: Architecture
title: Non-blocking calls on the tick
description: >-
  How a sketch makes parallel API calls without threads: a curl_multi pool
  pumped by the shuttle, delivering through the event queue and optional
  hooks.
tags: [surface, async, http, tick]
status: draft
generated: { by: claude-opus-5/claude-code, at: "2026-08-30T22:30:00Z" }
sources:
  - id: pool
    resource: src/Surface/Core/Async/HttpPool.php
    title: HttpPool
  - id: driver
    resource: src/Surface/Core/Async/MultiCurlDriver.php
    title: MultiCurlDriver
  - id: shuttle
    resource: src/Surface/Core/ProgramShuttle.php
    title: ProgramShuttle — callHttp, register, sink
  - id: tests
    resource: tests/Core/AsyncTest.php
    title: Async tests
---

# Moved down (2026-08-31)

The machinery now lives in **voyager/io-pools** (framework): Event/
EventQueue/EventSink/Tickable/TickRoster/HttpPool/PendingCall/HttpResult/
MultiCurlDriver. Surface consumes it: `SurfaceEvent extends Event` (family
= the type's backing value), the shuttle holds a framework queue + roster,
`callHttp()` delegates. Task events are base `Event`s — no window, no
SurfaceEventType; match by name or `family === 'task'`. Everything below
describes behaviour that is now the component's, seen from Surface.

# Overview

```php
$program->callHttp('api-somewhere', 'get', 'https://somewhere.net/api', $headers, $body)
    ->onSuccess(fn (HttpResult $r) => ...)
    ->onFail(fn (HttpResult $r) => ...);
// loop: $events->has('api-somewhere') — TASK event, payload = status/headers/body/error
```

For I/O-bound work, `curl_multi` IS the parallelism — many requests in
flight, one thread, the kernel waits. `harvest()` never blocks; the first
tick of a call costs a few ms of TLS setup, steady state is near zero.
Measured live: three parallel calls, completions staggered across ticks,
DNS failure through the fail lane, 200 and 404 both through success.[^driver]

# The pieces

- **`Tickable`** + `ProgramShuttle::register()` — the shuttle pumps
  registered tickables each tick, after the engine and layout. Anything
  periodic rides the loop.[^shuttle]
- **`HttpPool`** — one in-flight call per name (duplicate refused loudly,
  name freed on settle); each completion pushes
  `SurfaceEvent(TASK, <the author's name>, …)` through the shuttle's queue
  AND fires the `PendingCall` hook — the button convention, both lanes
  always.[^pool]
- **`HttpDriver`** — the transport seam. multi-curl ships; the slot exists
  for an ext-parallel COMPUTE driver later, not for HTTP (threads add
  nothing to I/O and ZTS-lock half the hardware).
- **`ProgramShuttle::sink()`** — outside sources (a future API package)
  can deliver through `events()` by taking the sink and registering as a
  tickable.

# Decisions

- **`ok` is transport truth only.** A 404 is a successful conversation;
  status rides the result and the sketch judges. `onFail` means DNS,
  refused, timeout.
- **Author-chosen event names pass through raw** (menu-event precedent);
  auto-generated events stay namespaced.
- **Forking is forbidden after connect()** — NSApplication and GTK are
  fork-unsafe post-init. This design exists so nobody needs to.

[^pool]: HttpPool
[^driver]: MultiCurlDriver
[^shuttle]: ProgramShuttle — callHttp, register, sink
[^tests]: Async tests
