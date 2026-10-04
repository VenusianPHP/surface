---
type: Runbook
title: Testing
description: Pest suite with fakes in a workbench of path repos.
resource: tests/
tags: [surface, pest]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-03T23:06:29Z }
sources:
  - id: fixtures
    resource: tests/Fixtures/
    title: FakeSession, FakeWindowDriver, FakePrimitives
---

# Overview

Suite is hardware- and toolkit-free: `FakeSession` (a `BridgedToolkitSession` recording pumps and wakes), `FakeWindowDriver`, and `FakePrimitives` (`FakeHost` window using `HostsPrimitives`, `FakePrimitiveFactory` with `$minted` and a `$lacking` kind list, one fake concrete per primitive whose hooks append to a public `$log`). Covers menu profile parsing, mail names, `ToolkitWindowManager`, session lifecycle, `joinLoop`, `postLatest` coalescing, `Color`/typography, and the primitive registry, containers and leaves.[^fixtures]

Splits require `venusian-voyager/*` 0.10; run in a scratch workbench, never the repo root:

1. Copy the tree (no vendor) to a scratch dir.
2. Add path repositories: `<framework>/src/Voyager/*` and `<surface>/src/Surface/*`, symlinked.
3. `composer install`, `php vendor/bin/pest`.
4. Delete the workbench.

Framebuffers: 43 golden fixtures (`tests/Framebuffers/fixtures`, 36–43 span painting) run through every driver (`FixtureRunner`); `ParityTest` feeds seeded writes and seeded spans into both flavors of 27 formats; `SpansTest` holds RGBA8 blending to an independent reference; `FakePixelStore` tests the five kinds with no real store. Extended tests skip when `FbBuffer` is not a class (no ext-fb, or the 0.8 one). To run without ext-fb, point `PHP_INI_SCAN_DIR` at a copy of the conf.d minus `30-fb.ini`: `php -n` also drops kqueue/epoll and skips three Bridge tests.

Rasterize: hand-worked goldens (`GoldenTest`) through both drivers × both edge modes; coverage summing to area and one-pixel lines against a float reference (`PropertyTest`); `StrokerTest`; `ValidationTest`; seeded native-vs-C parity (`ParityTest`); `PaintTest` rasterizes, paints and drains through every pairing of rasterize and framebuffer drivers. Extended cases skip when `RasterScanner` is not a class; omit `30-rasterize.ini` from the scan dir to run without it.

Real toolkit behaviour is tested in each jovian driver's suite.

[^fixtures]: FakeSession, FakeWindowDriver, FakePrimitives
