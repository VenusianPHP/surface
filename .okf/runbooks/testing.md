---
type: Runbook
title: Testing
description: Pest suite with fakes in a workbench of path repos.
resource: tests/
tags: [surface, pest]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-04T01:28:52Z }
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

Framebuffers: 43 golden fixtures (`tests/Framebuffers/fixtures`, 36–43 span painting) run through every driver (`FixtureRunner`); `ParityTest` feeds seeded writes and seeded spans into both flavors of 27 formats; `SpansTest` holds RGBA8 blending to an independent reference; `ImageTest` holds `paintImage()` to hand-worked bytes through every driver, and `ParityTest` feeds seeded placements into both flavors; `FakePixelStore` tests the five kinds with no real store. Extended tests skip when `FbBuffer` is not a class (no ext-fb, or the 0.8 one). To run without ext-fb, point `PHP_INI_SCAN_DIR` at a copy of the conf.d minus `30-fb.ini`: `php -n` also drops kqueue/epoll and skips three Bridge tests.

Rasterize: hand-worked goldens (`GoldenTest`) through both drivers × both edge modes; coverage summing to area and one-pixel lines against a float reference (`PropertyTest`); `StrokerTest`; `ValidationTest`; seeded native-vs-C parity (`ParityTest`); `PaintTest` rasterizes, paints and drains through every pairing of rasterize and framebuffer drivers. Extended cases skip when `RasterScanner` is not a class; omit `30-rasterize.ini` from the scan dir to run without it.

Drawing: `RenderingEngineTest` reads the commands a `RecordingEngine` (tests/Fixtures) is handed: frames, replay, dropped frames, transform order, every verb's lowering, limits. `VelvetTest` runs every pairing of rasterize and framebuffer drivers (`engine pairings` dataset) and holds frames to Rasterize + framebuffer called directly, plus each kind's protocol. `DrawingManagerTest` covers arguments and refusals. `tests/Fixtures/FakeManagers.php` builds the managers without a container.

Images: `PngWriter` and `TiffWriter` (tests/Support/Images) write each file sample by sample (palettes, tRNS, 16-bit, Adam7; byte orders, strips, tiles, PackBits, LZW, Deflate, predictor), so `PngTest` and `TiffTest` expect hand-worked RGBA8; native PNG alpha through `Pixels::asPng()` (gd's 7 bits). `JpegTest`: gd-written and two ImageMagick fixtures (grey, Adobe CMYK) within a tolerance. `ImagesTest`: sniffing, refusals, minting. `ImagesManagerTest`: config. `ParityTest`: both drivers, same JPEG bytes, same PNG bytes but for gd's alpha, the 16-bit tRNS split. `image decoders` dataset: native always, extended when `imgdec_png()` exists. `WriteRgba8Test` (Framebuffers): every kind, both flavors.

Real toolkit behaviour is tested in each jovian driver's suite.

[^fixtures]: FakeSession, FakeWindowDriver, FakePrimitives
