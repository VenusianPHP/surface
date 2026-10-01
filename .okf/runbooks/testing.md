---
type: Runbook
title: Testing
description: Pest suite with fakes in a workbench of path repos.
resource: tests/
tags: [surface, pest]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-01T20:04:54Z }
sources:
  - id: fixtures
    resource: tests/Fixtures/
    title: FakeSession, FakeWindowDriver
---

# Overview

Suite is hardware- and toolkit-free: `FakeSession` (a `BridgedToolkitSession` recording pumps and wakes) and `FakeWindowDriver`. Covers menu profile parsing, mail names, `ToolkitWindowManager`, session lifecycle and `joinLoop`.[^fixtures]

Splits require `venusian-voyager/*` 0.10; run in a scratch workbench, never the repo root:

1. Copy the tree (no vendor) to a scratch dir.
2. Add path repositories: `<framework>/src/Voyager/*` and `<surface>/src/Surface/*`, symlinked.
3. `composer install`, `php vendor/bin/pest`.
4. Delete the workbench.

Real toolkit behaviour is tested in each jovian driver's suite.

[^fixtures]: FakeSession, FakeWindowDriver
