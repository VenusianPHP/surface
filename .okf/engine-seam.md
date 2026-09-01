---
type: Decision
title: A container alias is the only seam to an engine
description: >-
  Surface names an engine by resolving a container string and nothing else.
  No import, no class_exists, no package awareness of any kind.
tags: [surface, bridge, layering, decision]
status: draft
generated: { by: claude-opus-5/cursor, at: "2026-08-29T21:00:00Z" }
sources:
  - id: mac-action
    resource: src/Surface/Bridge/Actions/BuildMacOSSession.php
    title: BuildMacOSSession
  - id: composer
    resource: composer.json
    title: Package metadata 0.8.0
  - id: appkit-projection
    resource: https://github.com/jovian/appkit/blob/main/.okf/projection-rule.md
    title: jovian/appkit projection rule
---

# Decision

Surface resolves the string `mac.bridge` or `linux.bridge` out of the
container, calls `connect()` on whatever comes back, and knows nothing
else.[^mac-action] Each `jovian/venusian-*` service provider binds its own
session singleton behind that alias. **Installing the package is the whole
of the enablement.**

`composer.json` suggests the engine packages and requires neither.[^composer]
Surface's only hard dependency is `venusian-voyager/nuts-and-bolts`.

# Rejected: probing for the package

Checking `class_exists()` or `method_exists()` before resolving was
considered and rejected. Any such probe names a symbol from an engine
package, which makes Surface aware of that package's code — exactly the
coupling this layer exists to avoid. A bare string literal is not code
awareness; a class name is.

The consequence is accepted deliberately: with no engine package installed,
`connect()` raises the container's own `NotFoundExceptionInterface` rather
than a friendly Surface error. The PSR exception already states the truth.

# Where an engine failure surfaces instead

Failures the engine *can* report get a Surface type.
`Surface\Contracts\Bridge\BridgeException` extends `SurfaceLevelException`,
and each engine package subclasses it — `AppKitBridgeException`,
`GTKBridgeException`. A sketch catches every bridge failure by one type
without Surface ever naming AppKit or GTK.

# Why this layer exists at all

The stack is four deep, and each level forbids the one below it from
composing:[^appkit-projection]

```text
ext-appkit / ext-gtk        1:1 binding, zero opinion
  └── jovian/appkit, jovian/gtk      enums + typed DTO projection, no composition
        └── jovian/venusian-*        composition, one engine each
              └── venusian/surface   cross-platform abstraction
```

A shared abstraction over AppKit and GTK is Surface's job specifically
because `jovian/appkit` and `jovian/gtk` are shape-parallel and share no
code. Building it one layer down would couple them.

See [bridge-lifecycle](/bridge-lifecycle.md) for what crosses the seam.

[^mac-action]: BuildMacOSSession
[^composer]: Package metadata 0.8.0
[^appkit-projection]: jovian/appkit projection rule
