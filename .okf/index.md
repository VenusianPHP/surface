---
okf_version: "0.2"
---

# venusian/surface — knowledge bundle

Surface is the engine-agnostic layer for native OS windows, GPU drawing to
window views, and drawing to embedded IC displays. Only the first of those
has been started.

The package is mid-rebuild. The 0.8 native-window view tree was written
against an older, opinionated `ext-appkit` / `ext-gtk` whose convenience
calls no longer exist, so it was torn out and is being rebuilt on the
strict 1:1 bindings via `jovian/appkit` and `jovian/gtk`.

What stands today: the OS bridge lifecycle, native windows with menu-bar
profiles and an event queue, and five conjured view kinds — label, button,
spinner, image, video — placed, centred, wrapped, styled and played in
top-left pixels on both engines.

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
  bridge: session mints, driver holds, ProgramShuttle pairs the two
* [menu-profiles.md](/menu-profiles.md) - named engine-neutral menu
  definitions, per-window election, and each engine's role table
* [views.md](/views.md) - conjured nodes: Surface owns the name registry and
  the top-left frame, engines translate through four hooks
* [async.md](/async.md) - non-blocking parallel calls on the tick: curl_multi
  pool, TASK events, hooks; the transport seam and the fork prohibition
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
| Split packages | `surface/bridge`, `surface/contracts`, `surface/native-windows` |
| Hard dependencies | `venusian-voyager/nuts-and-bolts` + `venusian-voyager/io-pools` |
| Engines | suggested, never required |
| Tests | `vendor/bin/pest` green at 155; orphaned view tests excluded in `phpunit.xml` |
