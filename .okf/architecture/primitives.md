---
type: Module
title: Toolkit primitives
description: Native widgets inside a ToolkitWindow - containers, registry, paths, placement, factory, apply hooks, engine callbacks.
resource: src/Surface/Windows/Primitives/
tags: [surface, windows, primitives, widgets]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-04T03:04:28Z }
sources:
  - id: contracts
    resource: src/Surface/Contracts/Windows/Primitives/
    title: Primitive contracts
  - id: abstracts
    resource: src/Surface/Windows/Primitives/
    title: Primitive abstracts, registry, placement, HostsPrimitives
  - id: spec
    resource: docs/superpowers/specs/2026-10-02-toolkit-primitives-design.md
    title: Toolkit primitives design
---

# Overview

Primitive = native view in a `ToolkitWindow`. Toolkit owns layout: PHP declares containers + child properties, toolkit sizes and reflows, sizes read back on demand. Three layers:[^spec]

| Layer | Where | Holds |
|---|---|---|
| Contracts | `Surface\Contracts\Windows\Primitives` | `TK*` interfaces, `Align`, `ImageScaling`, `TableColumn`, `Placement`, `PrimitiveRegistry`, `PrimitiveFactory`, `HasEnabledState` |
| Abstracts | `Surface\Windows\Primitives` | `TK*` abstracts (state + guards + `apply*` hooks), `HostsPrimitives` |
| Concretes | `Jovian\Toolkits\<Kit>\Primitives` | `<Kit><Name> extends TK<Name>`: native build, hooks, mail |

Entry: `app('toolkit-windows')->open(...)` → window → one content container → everything else created on containers.

```php
$window = app('toolkit-windows')->open('main', 640, 400, 'main');
$main = $window->column('main', spacing: 8, padding: 12);
$main->row('toolbar')->button('save', 'Save');
$window->view('main.toolbar.save');      // dotted path from the content container
$window->uuid($uuid);                     // uuid4
$main->view('toolbar.save');              // relative path from a container
```

# Identity

* Name `[A-Za-z0-9_-]+` (whole string, no trailing newline), unique among siblings. Path = dotted names from the content container (`main.toolbar.save`). uuid4 from `Str::uuid()`.[^abstracts]
* `PrimitiveRegistry` per window: path → primitive, uuid → primitive. `register()` refuses a taken path; `forget()` drops both.
* Window: `content()`, `view(path)`, `uuid(uuid)` (closed window → `WindowException`). Container: `view(relativePath)`, `children()` (display order).

# Containers

`TKPrimitiveGroup` holds every creation method (`label` … `video`, `column`, `row`, `grid`, `fixed`, `scrollView`), one signature on every container kind. `TKColumn`/`TKRow`/`TKGrid`/`TKFixed` extend `TKGroup`; `TKScrollView` extends `TKPrimitiveGroup`.[^contracts]

| Container | Placement | Extra |
|---|---|---|
| `TKColumn` / `TKRow` | creation order (`Placement::next()`) | `spacing()`, `setSpacing()`; children reorder themselves: `$child->moveBefore($sibling)`, `moveAfter($sibling)`, `moveTo($index)` (final position); outside a column/row → `WindowException` |
| `TKGrid` | `at(row, column, rowSpan = 1, columnSpan = 1)` before each creation (`Placement::cell`) | covered cell taken → `WindowException`; `spacing()`, `setSpacing()` |
| `TKFixed` | `at(x, y, width, height)` before each creation (`Placement::frame`) | `move(child, x, y)`, `resize(child, w, h)`, `frameOf(child)` = current frame (creation frame, then every move/resize; recorded before `insertNative()`); only place pixels exist |
| `TKScrollView` | one column/row/grid/fixed | leaf or second child → `WindowException`; `content()`, `setScrollbars(h, v)` |

Window content: `$window->column|row|grid|fixed(name)` once; second → `WindowException`. `HostsPrimitives` (trait for driver windows) implements content, registry, lookups; window supplies `factory()`, `mountContent()`, `size()`, and calls `removeContent()` from `close()` while its native lives.

# Creation

1. Admit (before any native): container not removed, name valid, no sibling holds it, kind rule (grid needs `at()` + free cells, fixed needs `at()`, scroll view takes one group).
2. Mint: `$window->factory()->mint<Kind>($host, …)`. Factory = driver's; builds concrete with `$host->takePlacement()` (on the contract group) (`null` host = content container, `Placement::next()`). Kind engine lacks → `WindowException("TK<Kind> is not available on <kit>.")` (Qt: `TKToggle`, `TKSpinner`).
3. Adopt: registry `register()`, add to children, `insertNative($child)`.

Refusal or factory exception clears the pending `at()`. Concrete constructors call `parent::__construct()` first (validates name + kind arguments), then build the native from constructed state; abstracts never call a hook from a constructor.

# Base state

Every primitive: `setVisible`/`show`/`hide`, `setEnabled`/`enable`/`disable` (`HasEnabledState`), `watchSize(bool)`, `setBackground(?Color)`, `fill(h, v)`, `align(Align h, Align v = FILL)`, `minSize(w, h)` (>= 0), `size()` (engine read). Visible, enabled, watch: change-only. Setter stores, then calls `apply<Thing>()`.

# Removal

`remove()`: terminal. Group removes children first (deepest first). Each: watch off (`applyWatchSize(false)`, where the driver also `forgetLatest()`s its pending `ViewResized`), `destroyNative()`, registry forget, leave parent (`forgetChild()`; content container: `window->forgetContent($this)`). Both forgets accept only a removed member, else `WindowException`. After: every setter and native read → `WindowException("Primitive '<path>' was removed.")`; plain state getters return last state. Window close removes the tree.

# Leaves

| Abstract | Ctor args | Hooks | Engine callback |
|---|---|---|---|
| `TKLabel` | text | `applyText`, `applyWrap`, `applyAlignment`, `applyFont`, `applyTextColor` | — |
| `TKButton` | label | `applyLabel`, `applyFont`, `applyTextColor` | — (driver posts `ButtonClicked`) |
| `TKImage` | ?file | `applyFile`, `applyScaling` | — |
| `TKCanvas` | — | `nativeScale`, `applyPixels(rgba8, w, h)` | — |
| `TKSeparator` | horizontal | — (`isHorizontal()`) | — |
| `TKSpinner` | — | `applySpinning` (change-only) | — |
| `TKProgressBar` | ?fraction (0..1, null = indeterminate) | `applyFraction` | — |
| `TKTextInput` | value, ?placeholder, secret | `applyValue`, `applyPlaceholder`, `applyFont`, `applyTextColor` | `nativeValueChanged(string)` |
| `TKTextArea` | value | `applyValue`, `applyFont`, `applyTextColor` | `nativeValueChanged(string)` |
| `TKCheckbox` | label, checked | `applyLabel`, `applyChecked` | `nativeToggled(bool)` |
| `TKToggle` | on | `applyOn` | `nativeToggled(bool)` |
| `TKToggleButton` | label, pressed | `applyLabel`, `applyPressed` | `nativeToggled(bool)` |
| `TKSlider` | min, max, value | `applyValue` (clamped), `applyRange` | `nativeValueChanged(float)` |
| `TKDropdown` | list<string> options, selected | `applyOptions` then `applySelected` | `nativeSelected(int)` |
| `TKDatepicker` | ?date | `applyDate` (null = native default, `date()` stays null) | `nativeDateChanged(DateTimeImmutable)` |
| `TKTable` | list<TableColumn>, rows | `applyRows(list<list<string>>)` then `applySelectedRow(?int)` | `nativeRowSelected(?int)` |
| `TKVideo` | ?file | `applyFile`, `applyPlay`, `applyPause`, `applySeek`, `applyMuted`, `applyLoop`; reads `nativePosition()`, `nativeDuration()` | `nativeStateChanged(bool playing)` |

* Guards (`WindowException`): NAN/INF in a progress fraction, slider range or value, or `seek()`; fraction outside 0..1, slider `min >= max`, dropdown index out of range / options not a list of strings, table row out of range / cell not scalar|null / column ids not unique, `seek()` < 0.
* Dropdown: `selected` = -1 exactly when no options; `setOptions` keeps an in-range selection else 0.
* Table: rows normalised to every column in column order, missing key `''`; `setRows`/`clearRows` clear selection; `appendRow` keeps it; every reload re-applies the selection.
* Video: `isPlaying()` = engine's last report, not the last call.

# Mail

Code-driven changes post nothing. Engine callbacks (`native*`) record state only, never guarded, never post: they run inside native dispatch. Driver posts the [view mail](/api/view-mail.md) right after the callback; resize mail goes through `postLatest()` ([bridge](/architecture/bridge.md)). Typography: [styling](/api/styling.md).

[^contracts]: Primitive contracts
[^abstracts]: Primitive abstracts, registry, placement, HostsPrimitives
[^spec]: Toolkit primitives design

# Canvas

`TKCanvas` = rectangle the application draws. Toolkit lays it out, never paints it. A `Surface\Contracts\Drawing\Output`, like an [embedded display](embedded-displays.md).

```php
$view = $column->canvas('view')->fill();
$fb = $view->framebuffer('dirty');                       // RGBA8, bound to the canvas, sized to it in device pixels
$velvet = app('drawing')->renderer('velvet', ['framebuffer' => $fb]);
$velvet->frame($draw);
$view->present();                                        // on screen
```

* `pixelSize()` = `size()` × display scale. `[0, 0]` before layout.
* `framebuffer(kind = 'full', ?width, ?height, frames = 2, ?driver)`: `full`, `dirty` or `ring`. Same one answered while kind + size (+ frames, + named driver) match; else new one made and bound. After a resize: call again. Smaller size given = stretched over the view. No size given + not laid out yet: `WindowException`.
* `present()`: full → always. Dirty → first time, then only with damage; begins a new epoch. Ring → front frame, once per frame presented. Shown opaque: alpha byte ignored.
* Driver from the app's `FramebufferManager` (`WindowsServiceProvider` sets `TKCanvas::resolveFramebuffersUsing()`); with none set, the canvas builds `native` / `extended` itself.
* Copy path: toolkit takes a whole image per present. A toolkit's canvas implements `nativeScale()` + `applyPixels()` only.
