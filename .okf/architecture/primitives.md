---
type: Module
title: Toolkit primitives
description: Native widgets inside a ToolkitWindow - containers, registry, paths, placement, factory, apply hooks, engine callbacks.
resource: src/Surface/Windows/Primitives/
tags: [surface, windows, primitives, widgets]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-19T03:30:00Z }
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
| Contracts | `Surface\Contracts\Windows\Primitives` | `TK*` interfaces, `Align`, `ImageScaling`, `TableColumn`, `TreeNode`, `Placement`, `PrimitiveRegistry`, `PrimitiveFactory`, `HasEnabledState` |
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

`TKPrimitiveGroup` holds every creation method (`label`, `link` … `video`, `column`, `row`, `grid`, `fixed`, `scrollView`, `split`, `tabs`), one signature on every container kind. `TKColumn`/`TKRow`/`TKGrid`/`TKFixed` extend `TKGroup`; `TKScrollView` and `TKSplitView` extend `TKPrimitiveGroup`; `TKTabView` is a group in the abstracts only (its contract extends `TKPrimitive`).[^contracts]

| Container | Placement | Extra |
|---|---|---|
| `TKColumn` / `TKRow` | creation order (`Placement::next()`) | `spacing()`, `setSpacing()`; children reorder themselves: `$child->moveBefore($sibling)`, `moveAfter($sibling)`, `moveTo($index)` (final position); outside a column/row → `WindowException` |
| `TKGrid` | `at(row, column, rowSpan = 1, columnSpan = 1)` before each creation (`Placement::cell`) | covered cell taken → `WindowException`; `spacing()`, `setSpacing()` |
| `TKFixed` | `at(x, y, width, height)` before each creation (`Placement::frame`) | `move(child, x, y)`, `resize(child, w, h)`, `frameOf(child)` = current frame (creation frame, then every move/resize; recorded before `insertNative()`); only place pixels exist |
| `TKScrollView` | one column/row/grid/fixed | leaf or second child → `WindowException`; `content()`, `setScrollbars(h, v)` |
| `TKSplitView` | creation order; each child a pane | `split(name, horizontal = true)`; `positions()` / `setPositions(list<float>)`: one fraction per divider between *visible* panes, ascending in 0..1, else `WindowException`; read from the native, so it follows drags; a scroll view may hold one |
| `TKTabView` | `addPage(name, title)` → the page's `TKColumn`; any other child call → `WindowException` | `pages()`, `title()` / `setTitle()`, `selected()` / `select()`; first page selected; removing a page's column removes its tab |

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
| `TKLink` | text, url | `applyText`, `applyUrl`, `applyAlignment`, `applyFont` | — |
| `TKButton` | label | `applyLabel`, `applyFont`, `applyTextColor`, `applyRole` | — (driver posts `ButtonClicked`) |
| `TKImage` | ?file | `applyFile`, `applyScaling` | — |
| `TKCanvas` | — | `nativeScale`, `applyPixels(rgba8, w, h, damage)`, `applyAddress(addr, w, h, stride, damage)` | — |
| `TKSeparator` | horizontal | — (`isHorizontal()`) | — |
| `TKSpinner` | — | `applySpinning` (change-only) | — |
| `TKProgressBar` | ?fraction (0..1, null = indeterminate) | `applyFraction` | — |
| `TKTextInput` | value, ?placeholder, secret | `applyValue`, `applyPlaceholder`, `applyFont`, `applyTextColor` | `nativeValueChanged(string)` |
| `TKTextArea` | value (line endings normalised to "\n"), read-only, wrap, line backgrounds, range styles, selection, scroll position, scroll watching | `applyValue`, `applyFont`, `applyTextColor`, `applyReadOnly`, `applyWrap`, `applyLineBackgrounds`, `applyRangeStyles`, `applySelectLines`, `applyScrollTo`, `applyScrollToLine`; reads `nativeSelectedLines`, `nativeScrollPosition` | `nativeValueChanged(string)` |
| `TKCheckbox` | label, checked | `applyLabel`, `applyChecked` | `nativeToggled(bool)` |
| `TKToggle` | on | `applyOn` | `nativeToggled(bool)` |
| `TKToggleButton` | label, pressed | `applyLabel`, `applyPressed` | `nativeToggled(bool)` |
| `TKSlider` | min, max, value | `applyValue` (clamped), `applyRange` | `nativeValueChanged(float)` |
| `TKDropdown` | list<string> options, selected | `applyOptions` then `applySelected` | `nativeSelected(int)` |
| `TKDatepicker` | ?date | `applyDate` (null = native default, `date()` stays null) | `nativeDateChanged(DateTimeImmutable)` |
| `TKTable` | list<TableColumn>, rows | `applyRows(list<list<string>>)` then `applySelectedRow(?int)` | `nativeRowSelected(?int)` |
| `TKTree` | list<TableColumn>, list<TreeNode> | `applyNodes`, `applyExpanded(id, bool)` (ancestors all expanded), `applySelected(?id)`, `nativeSelected()` | `nativeNodeExpanded(id, bool)` → changed |
| `TKVideo` | ?file | `applyFile`, `applyPlay`, `applyPause`, `applySeek`, `applyMuted`, `applyLoop`; reads `nativePosition()`, `nativeDuration()` | `nativeStateChanged(bool playing)` |

A link's URL needs a scheme (WindowException otherwise, before the native exists or changes); the toolkit opens it on a click and nothing posts.

* Guards (`WindowException`): NAN/INF in a progress fraction, slider range or value, or `seek()`; fraction outside 0..1, slider `min >= max`, dropdown index out of range / options not a list of strings, table row out of range / cell not scalar|null / column ids not unique, `seek()` < 0.
* Dropdown: `selected` = -1 exactly when no options; `setOptions` keeps an in-range selection else 0.
* Table: rows normalised to every column in column order, missing key `''`; `setRows`/`clearRows` clear selection; `appendRow` keeps it; every reload re-applies the selection.
* Tree: ids unique across the forest (`WindowException`, nothing changed). Expansion recorded in Surface, nodes with children only; kept while an ancestor is collapsed, re-applied by `reopen()` when it opens. `expand()` on a leaf = no-op. `setNodes` keeps expansion and selection for ids still present; a kept selection now under a collapsed node opens its ancestors, as `selectNode()` does. Selection read from the native.
* Video: `isPlaying()` = engine's last report, not the last call.

# Mail

Code-driven changes post nothing. Engine callbacks (`native*`) record state only, never guarded, never post: they run inside native dispatch. Driver posts the [view mail](/api/view-mail.md) right after the callback; resize mail goes through `postLatest()` ([bridge](/architecture/bridge.md)). Typography: [styling](/api/styling.md).

[^contracts]: Primitive contracts
[^abstracts]: Primitive abstracts, registry, placement, HostsPrimitives
[^spec]: Toolkit primitives design

# Canvas

`TKCanvas` = rectangle the application draws. Toolkit lays it out, never paints it. A `Surface\Contracts\Drawing\Pipeable` output target, unlike an [embedded display](embedded-displays.md).

```php
$view = $column->canvas('view')->fill();
$fb = $view->framebuffer('dirty');                       // RGBA8, bound to the canvas, sized to it in device pixels
$velvet = app('drawing')->renderer('velvet', ['framebuffer' => $fb]);
$velvet->frame($draw);
$view->present();                                        // on screen
```

* `pixelSize()` = `size()` × display scale. `[0, 0]` before layout.
* `framebuffer(kind = 'dirty', ?width, ?height, frames = 2, ?driver)`: `dirty`, `full` or `ring`. Same one answered while kind + size (+ frames, + named driver) match; else new one made and bound. After a resize: call again. Smaller size given = stretched over the view. No size given + not laid out yet: `WindowException`.
* `present()`: full → always. Dirty → first time, then only with damage; begins a new epoch. Ring → front frame, once per frame presented, with the damage since the serial last shown. Shown opaque: alpha byte ignored.
* `canPipe(Framebuffer)`: true of an extended RGBA8 framebuffer — its bytes are in C memory. A pipable framebuffer goes to the toolkit by `applyAddress(pointer, w, h, stride, damage)`, no pixel byte through PHP; a PHP-held one goes as a string through `applyPixels()` as before.
* Driver from the app's `FramebufferManager` (`WindowsServiceProvider` sets `TKCanvas::resolveFramebuffersUsing()`); with none set, the canvas builds `native` / `extended` itself.
* A toolkit's canvas implements `nativeScale()` + `applyPixels()` + `applyAddress()`; the toolkit copies what it needs before returning.

Focus: every primitive has `focus()` (throws `WindowException` when removed, hidden or disabled itself or through a container, or when the native declines; the native is not asked in those cases) and `hasFocus()`, through the abstract hooks `applyFocus(): bool` and `nativeHasFocus()`; the driver focuses the native's focus target (the inner view of a scroll-wrapped native). A canvas takes no focus until `setFocusable(bool $on = true, bool $takesTab = false)`; `takesTab` is false whenever focusable is, and a change reaches the driver's `applyFocusable($on, $takesTab)` once. `FocusGained` / `FocusLost` post only for the user's focus changes.

Pointer: `watchPointer(bool $on = true)` / `isWatchingPointer()`. Off by default; the flag lives in the abstract and nothing is written to the native (as a context menu is not) — the window's pointer source reads it. `watchPointer()` throws `WindowException` once the canvas is removed. While watching, the canvas posts `CanvasPressed`, `CanvasDragged`, `CanvasReleased` and `CanvasWheeled` ([view mail](/api/view-mail.md)) in points from its top-left, as `CanvasClicked`'s. `CanvasClicked` and the right-click rules post either way, so a sketch that wants only clicks never watches.

* One press at a time per window: the first button down on a watching, enabled canvas owns it; other buttons' presses and releases post nothing until it ends. `CanvasDragged` posts per move while that button is held, only when the point changed, and goes on outside the canvas and the window (implicit capture).
* Exactly one `CanvasReleased` per `CanvasPressed`: at the owning button's release, or at once at the last point posted when the toolkit ends the press without one (a context menu opens, GTK's grab breaks, a touch is cancelled). A press ended from code — `watchPointer(false)`, the canvas or a group above it disabled, `remove()`, the window closed — drops silently, as no mail comes from code.
* A toolkit-emulated touch is the left button. `clicks` counts presses of the same button on the same canvas within the toolkit's double-click time and distance, so a triple click is 3 everywhere.
* A wheel over a watching, enabled canvas is the canvas's: posted and not passed on, so an enclosing scroll view stays put. Not watching, it passes as before.

Surface lending: `TKCanvas` is a `WindowOutput` (`Surface\Contracts\Drawing`). A GPU engine borrows a native surface inside the canvas instead of drawing into its framebuffer.

* `surfaces()`: `[]` until a toolkit's canvas overrides it, with `makeSurface(kind, handles): array` (the surface's handles by name, always `kind->handle()`) and `removeSurface(kind)`. Both have default bodies.
* `lend(kind, borrower)`: one at a time; a kind not in `surfaces()` throws `WindowException` naming the kinds it lends. Handles come from the borrower's `lendingHandles()`. `LentSurface::size()` reads `pixelSize()` live.
* While lent: `boundFramebuffer()` = the borrower's, `framebuffer()` throws, `present()` = `borrower->presentInto()`. Skipped when the borrower's framebuffer has no damage since the last landed copy; epoch begun only when the copy lands, so a skipped copy (no free drawable) is retried next `present()`.
* `reclaim()`: removes the native surface, releases the `LentSurface`; the next `present()` of the canvas's own framebuffer is whole. `remove()` reclaims first, while the native view exists.
