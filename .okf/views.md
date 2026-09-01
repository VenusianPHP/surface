---
type: Architecture
title: Conjured views
description: >-
  How a sketch puts a node inside a window: Surface owns the name registry
  and the top-left frame, engines translate through four hooks.
tags: [surface, native-windows, views, coordinates]
status: draft
generated: { by: claude-opus-5/claude-code, at: "2026-08-30T08:00:00Z" }
sources:
  - id: view
    resource: src/Surface/NativeWindows/Views/View.php
    title: View — frame truth, centre/hug arithmetic, terminal removal
  - id: label
    resource: src/Surface/NativeWindows/Views/Label.php
    title: Label
  - id: windowable
    resource: src/Surface/NativeWindows/Windowable.php
    title: Windowable — conjure + registry
  - id: tests
    resource: tests/NativeWindows/LabelTest.php
    title: Label tests
---

# Overview

The rebuilt view tree, flat for now: nodes are conjured straight into the
window content, no containers yet. Kinds so far: **label**, **button**,
**spinner**, **image**, **video**.

```php
$window->label('title', 'Hello World!', 0, 0, 1, 1)
    ->align(TextAlignment::CENTER)
    ->hug()
    ->center();
```

`Windowable::label()` guards the name, asks the engine hook `mintLabel()`
for the native node, registers it, places it.[^windowable] `view($name)`
reads it back. Coordinates are **top-left absolute pixels inside the
content** — Surface's promise on both engines.

# Placement is a rule, not a result

`View` stores what it was told — `PlacementRule` ABSOLUTE, CENTER or
CENTER_X (horizontal centre, y anchored — the rule a top-anchored card
lives by), `SizeRule` FIXED, NATURAL or WRAP — and `relayout()` re-resolves them
against the current content size: NATURAL re-measures, WRAP keeps the
width and re-measures the height for text flowed at it, CENTER floors
`(content - size) / 2`, ABSOLUTE keeps x,y. `place()` records absolute +
fixed; `center()` and `hug()` flip one rule each and re-resolve. Engines
only ever receive a decided frame through `applyFrame()`.[^view]

That is what makes resize effortless, HTML/CSS style: nothing is told to
move. `ProgramShuttle::tick()` pumps, then calls `syncLayout()` on every
window; a window whose `contentSize()` changed re-resolves all its views
and pushes `window.resized.<window>` with `{width, height}`. The sketch
is not in the loop — the event exists for a sketch that wants to react
beyond layout. Detection is a uniform poll on both engines (GTK4 has no
resize signal; AppKit could push `windowDidResize:` but one path wins).
The first real size counts as a resize, which is what lays GTK out once
its content stops reading 0x0 — conjuring before `present()` is fine now.

**Open, measured later:** macOS live-resize runs AppKit's tracking loop
inside the pump, so layout may snap on mouse-up rather than track the
drag. If that reads badly, the fix is the `windowDidResize:` delegate
callback, which fires inside the tracking loop. Native springs
(`autoresizingMask`, `halign`/`valign`) were rejected — `GtkFixed` ignores
the latter, and rules stay Surface-side so both engines just receive
frames.

Four hooks per node kind:

| Hook | AppKit | GTK |
|---|---|---|
| `applyFrame` | `setFrame(NSRect(x, H - y - h, w, h))` — **the inversion lives here** | `GtkFixed::move` + `setSizeRequest` |
| `measure` | `sizeToFit()` then read the frame | `measure(HORIZONTAL/VERTICAL, -1)['natural']`, size request lifted first |
| `applyText` | `setStringValue` | `setText` |
| `destroyNative` | `removeFromSuperview` | `GtkFixed::remove` |

Label adds `applyAlignment`: `NSTextAlignment` on AppKit; `setJustify` plus
`setXalign` on GTK, because justify only affects multi-line and xalign is
what centres a single line inside a wider frame.[^label]

# The engine asymmetries this slice pays for

- **AppKit is the unlucky loser of the inversion** — decided. `AppKitLabel`
  flips every frame against `contentSize()` height. GTK's `GtkFixed` is
  top-left already; that is why the scaffold holds one.
- **Content size is authoritative on AppKit, a request on GTK.** GTK's
  content reads 0x0 until `present()` plus a pump; a `center()` before that
  resolves at the origin and is corrected by the first `syncLayout()`.
- **The scaffold's `GtkFixed` must expand.** A box gives it full width but
  only natural height — the extent of its children — so without
  hexpand/vexpand `contentSize()` answered `[400, ~20]` and centred labels
  went under the menubar.
- **`measure()` works pre-layout on both** — `sizeToFit` and
  `gtk_widget_measure` answer without a pump — so `hug()` needs no wait.
- **PHP class names are case-insensitive**: `GTKLabel` collides with the
  `GtkLabel` binding import, which is aliased `GtkLabelWidget` wherever
  both appear.

# Buttons and hooks

`$window->button('go', 'Go', x, y, w, h)->onClick($fn)` — the engine wires
its native click (`Bridge::setAction` shared target on AppKit, `clicked`
signal on GTK) to the abstract's `fireClick()`, which invokes the stored
hook. The hook runs INSIDE the pump that delivered the click — a
deliberate exception to the menu rule: `onClick` is the sketch author's
own closure against their own state, not a definition crossing an engine
seam. One hook per button; a second `onClick` replaces the first. Shared
frame mechanics live in per-engine traits (`TranslatesAppKitFrames` — the
inversion, `sizeToFit`; `TranslatesGtkFrames` — move/size-request,
pre-layout measure) so new control kinds stop copying them.

# Spinners, images, wrapped labels

`$window->spinner('busy', x, y, w, h)` — indeterminate only, conjured
stopped; `start()`/`stop()` cross through `applySpinning(bool)`. No
determinate bar: the HttpPool cannot report mid-flight progress and
Surface will not fake one. AppKit `NSProgressIndicator` (SPINNING style,
hidden when stopped) is a plain NSView, not an NSControl, so it cannot
share `TranslatesAppKitFrames` — it pays the same inversion itself. GTK
`GtkSpinner` aliases `GtkSpinnerWidget` (case-insensitive collision,
again).

`$window->image('pic', $path|null, x, y, w, h)` — loaded from a **file
path**, the one loading story both engines share; bytes from `callHttp`
go through a temp file. `setPath()` swaps the picture (re-measures under
NATURAL). Proportional fit is baked in: `NSImageScaling`
PROPORTIONALLY_UP_OR_DOWN, `GtkContentFit` CONTAIN + can-shrink — without
can-shrink a big picture floors `measure()` at its full size and refuses
the frame.

`$label->wrap($width)` — the WRAP rule. `applyWrap()` turns wrapping on
(AppKit: single-line-mode off, word-wrap line break, preferredMaxLayoutWidth;
GTK: `setWrap(true)`); `measureWrappedHeight($width)` answers the flowed
height (AppKit: `cell()->cellSizeForBounds` at the wrap width — needed the
generator parenting fix so `NSTextFieldCell extends NSCell`; GTK:
`measure(VERTICAL, $width)`, size request lifted first). Text and font
changes re-measure like NATURAL; a centred wrapped label re-centres.

# Removal is terminal

`remove()` destroys the native node and frees the name — the handle is dead
after, no unref owed, same on both engines. Proven by fake.[^tests]

# Styling

`Color` (sRGB floats, `Color::hex()`), `FontSpec` + `FontWeight`,
`TextAlignment` — all in Contracts. The `StylesText` trait gives labels and
buttons `setTextColor()`, `setFont()`, and `textCSS()` (a small declaration
list: color, font-size, font-weight, font-family, background-color —
recognised declarations route through the typed setters, the rest are
ignored). `setBackground(Color)` sits on every view.

Engine translations are opinionated by design:

- **AppKit**: labels via `setTextColor`/`setFont` (`ComposesAppKitStyle`
  maps FontWeight to NSFontWeight doubles) and `setDrawsBackground`;
  buttons have no colour setters, so a styled title recomposes an
  `NSAttributedString` (attributes dict carries live handles) and
  background goes through the view's layer (`CGColor` crosses as raw
  pointer bits, valid while the NSColor lives).
- **GTK**: everything is CSS, GTK's own model. Each window's
  `CssEngine` holds one `GtkCssProvider` on the display; a styled view
  gets class `v-<window>-<view>` and a rule block; changes reload the
  provider and restyle live. Removal drops the block.

# Not in this slice

Containers, events from views, anchors/percent rules, alignment beyond
labels. The old
`tests/Views` fakes describe where those went last time; the exclude list
in `phpunit.xml` gets pruned as each kind lands.

[^view]: View — frame truth, centre/hug arithmetic, terminal removal
[^label]: Label
[^windowable]: Windowable — conjure + registry
[^tests]: Label tests
