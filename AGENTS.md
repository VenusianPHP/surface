# Agent guidelines — venusian/surface

## Knowledge Bundle (OKF)

This package ships an Open Knowledge Format bundle at [`.okf/`](.okf/)
(excluded from the Composer dist via `.gitattributes` `export-ignore`).
Before changing code or advising on this package: read
[`.okf/index.md`](.okf/index.md) first, open only the concepts the task
needs, prefer `status: stable` over `draft`. When you learn something
durable, update the affected concept(s) and append [`.okf/log.md`](.okf/log.md);
new or changed concepts stay `status: draft` until a human verifies them.

Do **not** create `.okf` folders under `src/Surface/Bridge`,
`src/Surface/NativeWindows`, or any other component tree — knowledge for
this package lives at the package root only.

## Where this package sits

`ext-appkit` / `ext-gtk` (1:1 binding, zero opinion) → `jovian/appkit`,
`jovian/gtk` (enums + typed projection) → `jovian/venusian-appkit`,
`jovian/venusian-gtk` (composition, one engine each) → **`venusian/surface`**
(cross-platform abstraction).

Surface is the top of that stack and the only layer allowed to reconcile the
two engines. Nothing below it may build a cross-platform abstraction.

## Current state — read this before assuming a feature exists

The package is **mid-rebuild**. The 0.8 native-window view tree was written
against an older, opinionated extension whose convenience calls no longer
exist, and was torn out.

What stands today is two slices: the OS bridge lifecycle, and provisioning /
holding / presenting a bare native window on top of it. **There are no views
inside that window.** Anything that draws, lays out, or styles does not
exist — do not assume a control, a frame, or a colour has a home yet. See
[`.okf/window-provisioning.md`](.okf/window-provisioning.md).

`tests/Views/**` and three files under `tests/NativeWindows/**` are orphaned
from that removal and reference deleted classes. They are **excluded in
`phpunit.xml`**, not deleted, so a bare `vendor/bin/pest` is green — see
[`.okf/testing.md`](.okf/testing.md) for why they were kept. Run them
deliberately with a path argument if you are mining them for the rebuild.

**Known deviation:** `src/Surface/Core` holds `SurfaceServiceProvider` and
`ProgramShuttle` but is not a split package — no `.gitattributes`, no
`LICENSE`, no entry in the root `replace` map. No component carries its own
`composer.json` either, unlike `venusian/framework`'s `src/Voyager/*`. Left
as-is deliberately; do not "fix" it in passing.

## Package rules (quick) — 0.8.x

- Composer: `venusian/surface` **0.8.0**. PHP `^8.4|^8.5|^8.6`.
- Namespace root is `Surface\` at `src/Surface`.
- **Split packages.** `surface/bridge`, `surface/contracts`, and
  `surface/native-windows` are subtree splits, each with its own
  `.gitattributes` and `LICENSE` under `src/Surface/*`, and each declared in
  the root `replace` map. A new component directory needs all three.
- **Never import a `Jovian\` symbol.** Surface resolves the container alias
  `mac.bridge` or `linux.bridge` and knows nothing else about an engine. No
  `class_exists`, no `method_exists`, no engine package name in code — a
  string literal is not code awareness, a class name is. See
  [`.okf/engine-seam.md`](.okf/engine-seam.md).
- **Engines are `suggest`, never `require`.** Hard dependencies are
  `venusian-voyager/nuts-and-bolts` and `venusian-voyager/io-pools` — the
  loop/event/async primitives live in the framework so headless sketches
  get them without Surface.
- **Contracts live under `Surface\Contracts\*`,** mirroring the component
  they describe. Exceptions descend from `SurfaceLevelException`; engine
  packages subclass `Surface\Contracts\Bridge\BridgeException` so a sketch
  catches one type without naming an engine.
- **Shared policy lives in the abstract, engine specifics in the hooks.**
  Guards, idempotency, and state belong to Surface so every engine package
  inherits them. See [`.okf/bridge-lifecycle.md`](.okf/bridge-lifecycle.md).
- **Pick the truth, do not inherit it.** The two engines report different
  truths about size, placement, resize, and window traits. Decide once,
  document it, and make both drivers translate. The measured differences are
  in [`.okf/engine-asymmetries.md`](.okf/engine-asymmetries.md), along with
  the three the window slice settled by accident rather than by choice.
- **Windows are minted by the session, held by the driver.** The engine
  session is the only engine-aware object Surface can reach, so
  `provisionNewWindow()` is the factory. The driver is a name-keyed registry
  with no engine knowledge, which is what keeps its policy fake-provable.
- **Key off the contract, never a delegate's fields.** Drivers register with
  `OSWindow::name()`. `Windowable` happens to expose a public readonly
  `$name`, so reaching for the property still works against every shipped
  delegate and only breaks on a contract-only implementation — which is what
  `tests/Support/Fakes/ContractOnlyLinuxWindow.php` exists to catch.
- Enums are int- or string-backed with FULLY UPPERCASE cases. **No class
  constants anywhere.** Prefer `is_null($var)` over `$var === null`.

## Verification

```bash
vendor/bin/pest                   # green; orphans excluded in phpunit.xml
vendor/bin/pest tests/Views       # deliberately red — the torn-out tree
php -l <file>                     # syntax gate for touched files
```

Surface has no application container in tests. Cover shared policy with
fakes that count calls — the session state machine, the window registry, the
shuttle are all provable with no extension present and no engine package
installed, and that is where the coverage belongs. Shared fakes live in
`tests/Support/Fakes`; add to them rather than minting a one-off.

If a rule needs an engine to prove, it belongs to the engine package, not
here. Engine sessions are proven on real hardware instead: macOS for AppKit,
the Pi over `fnk` for GTK.

`WindowManager` is the one live class with no coverage — its factories call
`config()`, which wants a container the suite does not have. Leave it
uncovered rather than dragging a container in.
