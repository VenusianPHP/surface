---
okf_version: "0.2"
---

# venusian/surface — knowledge bundle

Surface is the engine-agnostic layer for native OS windows, GPU drawing to
window views, and drawing to embedded IC displays. Native windows and the
engine-free GPU drawing contracts / Painter / GPUView are in, including
the four seam shapes (`LAYER` / `GL_CONTEXT` / `VULKAN_SURFACE` /
`HOST_WINDOW`), plus whole engine-owned windows (Stage). Engines: `metal`,
`opengl` (`jovian/venusian-ogx`), `vulkan`, `sdl3`. Embedded displays are
later slices.

The package is mid-rebuild. The 0.8 native-window view tree was written
against an older, opinionated `ext-appkit` / `ext-gtk` whose convenience
calls no longer exist, so it was torn out and is being rebuilt on the
strict 1:1 bindings via `jovian/appkit` and `jovian/gtk`.

What stands today: the OS bridge lifecycle, native windows with menu-bar
profiles and typed mail on the IOPool dock, and nineteen conjured view
kinds — label, button, spinner, image, video, textInput, textArea,
slider, toggle, toggleButton, checkbox, progressBar, dropdown, datePicker,
table, separator, gpu, plus group and scrollView containers with group-relative
layout — placed, centred, styled and evented in top-left pixels on both
engines. The Components layer (opinionated PrimeVue-style shapes composed
from these primitives, engine-free) is complete at 25 of 25. HumanInput
(keyboards, mice, game pads, game controllers, `input.<engine>` seam plus
attached IC circuits, `input` dock resource) is in on the Surface side; the
three engine packages are being built now.

Read this index first, then open only the concepts the task needs. Every
concept here is `status: draft` until a human verifies it.

# Concepts

* [bridge-lifecycle.md](/bridge-lifecycle.md) - the connect / disconnect /
  pump contract, the two flags behind it, and what each guards
* [engine-seam.md](/engine-seam.md) - why a container alias string is the
  only thing joining Surface to an engine package
* [engine-asymmetries.md](/engine-asymmetries.md) - where AppKit and GTK
  disagree, and which truth the abstraction has to pick
* [window-provisioning.md](/window-provisioning.md) - the slice above the
  bridge: session mints, driver holds, LiveApplication pairs the two
* [menu-profiles.md](/menu-profiles.md) - named engine-neutral menu
  definitions, per-window election, and each engine's role table
* [views.md](/views.md) - conjured nodes: Surface owns the name registry and
  the top-left frame, engines translate through four hooks; nineteen kinds
* [drawing.md](/drawing.md) - GPU regions: engine-free contracts, the
  Painter, GPUView, the per-tick frame pass; Rasterizer is Drawing2D
  over any Framebuffer; Affine and Geometry shared with Painter
* [stage.md](/stage.md) - engine-owned windows: hosts and engines by alias,
  one Drawing2D
* [human-input.md](/human-input.md) - keyboards, mice, game pads, game
  controllers: the `input.<engine>` seam, IC circuits, the `input` dock
  resource
* [components-to-come.md](/components-to-come.md) - Fonts: reserved, facts
  recorded
* [components.md](/components.md) - opinionated shapes over the primitives:
  one root Group, named parts, pure PHP — twenty-five built, including
  Datepicker and DataTable wrapping datePicker / table
* [async.md](/async.md) - the loop and the IOPool dock: the `os` resource,
  mail and `events()`, the `http` resource; the fork prohibition
* [testing.md](/testing.md) - suite scope, the shared fakes, and which
  directories are excluded from the default run

# Related bundles

* [jovian/venusian-appkit](https://github.com/jovian/venusian-appkit) - the
  macOS engine package that implements the bridge
* [jovian/venusian-gtk](https://github.com/jovian/venusian-gtk) - the Linux
  engine package that implements the bridge

# Fast facts

| | |
|---|---|
| Version | 0.8.0, PHP `^8.4\|^8.5\|^8.6` |
| Namespace | `Surface\` at `src/Surface` |
| Split packages | `surface/bridge`, `surface/contracts`, `surface/drawing`, `surface/embedded-panels`, `surface/fonts`, `surface/human-input`, `surface/native-windows`, `surface/stage` — each with own `composer.json`; Core is not split |
| Hard dependencies | `venusian-voyager/nuts-and-bolts` + `venusian-voyager/io-pools` |
| Engines | suggested, never required |
| Tests | `vendor/bin/pest` green at 520; orphaned view tests excluded in `phpunit.xml` |
