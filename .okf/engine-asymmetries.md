---
type: Reference
title: Where AppKit and GTK disagree
description: >-
  The measured differences between the two engines at jovian 0.8.0 that any
  Surface abstraction has to reconcile, and which truth was picked.
tags: [surface, appkit, gtk4, portability]
status: draft
generated: { by: cursor-grok-4.6/cursor, at: "2026-09-12T19:40:00Z" }
sources:
  - id: jovian-appkit
    resource: https://github.com/jovian/appkit
    title: jovian/appkit 0.8.0, read at src/NS and src/Runtime
  - id: jovian-gtk
    resource: https://github.com/jovian/gtk
    title: jovian/gtk 0.8.0, read at src/Gtk and src/Runtime
---

# Why this exists

Both packages are strict 1:1 projections that add no composition, so every
convenience, coordinate decision, styling path, and lifetime policy is
Surface's to invent. These are the points where the two engines report
different truths and the abstraction must pick one rather than inherit it.

Recorded from reading `jovian/appkit` and `jovian/gtk` at 0.8.0.[^jovian-appkit][^jovian-gtk]

# Resolved in the bridge slice

| | AppKit | GTK4 | Surface picked |
|---|---|---|---|
| Mandatory start | `sharedApplication()`, `finishLaunching()` | `Lifetime::boot()` | Both behind `initializeEngine()`, once per process |
| Loop | `Bridge::pump(float $seconds)` | `Bridge::pump(int $ms)`, may block up to the budget | Integer milliseconds, and a pump may block |
| Visible effect of connecting | Dock icon appears with no window | Nothing at all | Connecting promises the engine accepts conjuring, not that the OS shows anything |
| Unresponsive process | macOS marks it Not Responding when unpumped | GNOME pings windows, so a windowless process looks healthy | Not modelled; pumping is the sketch's duty either way |
| Un-init | None | None | Disconnect withdraws presence; process exit is the real teardown |

Activation policy is what makes the macOS cycle observable: `REGULAR` plus
`activate()` raises the Dock icon, `PROHIBITED` drops it. GTK has no
counterpart, so its connect and disconnect are honest no-ops.

# What provisioning ships today

The window slice landed with these already fixed in code. Recorded so the
current behaviour is readable without opening the delegates:

| | Shipped as | Still open |
|---|---|---|
| Traits | AppKit mints with `TITLED\|CLOSABLE\|MINIATURIZABLE\|RESIZABLE` OR'd at construction; GTK sets none of the four | GTK has no equivalent, so the two platforms do not agree on what a default window is |
| Placement | `NSRect(0, 0, w, h)` — the screen's **bottom-left** under AppKit's origin | nothing centres it, and `center()` is on `MacOSWindow` only |
| Size | AppKit content rect; GTK `setDefaultSize` | the GTK not-laid-out-yet phase is neither blocked on nor exposed |

# Open, and waiting on the window slice

**Size is authoritative on macOS and a hint on Linux.** An AppKit content
rect *is* the content size at construction. GTK's `setDefaultSize` is a
request; real allocation exists only after `present()` plus a pump, and
reads 0x0 before that. Either every window has a not-laid-out-yet phase
callers must tolerate, or Surface blocks on the first pump and presents both
as immediate.

**Placement does not exist on Linux.** GTK4 removed window positioning
outright. AppKit's constructor *requires* screen coordinates, bottom-left
origin. Surface cannot honestly promise "open at x, y".

**Traits are one argument versus N setters.** Titled, closable, resizable
and borderless are bits OR'd into an AppKit style mask at construction. On
GTK they are separate post-hoc calls. A spec object read once suits AppKit;
live setters suit GTK. Provisioning hard-codes the AppKit mask and leaves the
GTK four unset.

**Resize is push on one engine and absent on the other.** AppKit posts
`NSWindowDidResizeNotification`. GTK4 has no resize signal; you poll
`getWidth()` / `getHeight()`. Cheapest honest unification is for Surface to
pull on both every tick and synthesise its own event.

**Close unifies cleanly.** Both support a veto — `windowShouldClose:`
returning a bool through an `NSWindowDelegate`, and `close-request`
return-value writeback on GTK.

# Resolved in the control wave (2026-09-03)

| | AppKit | GTK4 | Surface picked |
|---|---|---|---|
| Programmatic writes | Setters are silent for the control-wave views — no action fires. `NSDatePicker::setDateValue:` and `NSTableView` `reloadData` / `selectRowIndexes:` are the exceptions: they re-enter the delegate | `changed` / `toggled` / `value-changed` / `notify::*` fire for setters too | Every GTK control twin, plus the AppKit date picker and table twins, carries an `applying` suppression flag (the PHP-side route the gap below predicted) |
| Text read-back | `stringValue()` / `NSText::string_()` | GtkEditable `getText()`; `GtkTextBuffer::getText(startOffset, endOffset, includeHidden)` — ext unreserved 2026-09-04, iters cross as char offsets, -1 = end | Both engines read back; `TextChanged` always carries the value |
| Scroll start | Unflipped document shows its BOTTOM first | Top, always | `AppKitScrollView` re-pins the viewport to the top on every extent write |
| Container | Plain `NSView` (clips only once layered) | Own `GtkFixed`, `setOverflow(HIDDEN)` clips | Group-relative frames via `View::layoutSpace()`; AppKit pays its inversion against the host's inner height |
| Bare stepper | `NSStepper` (arrows only) | Only `GtkSpinButton`, entry fused in | No stepper primitive — InputNumber composes TextInput + two Buttons at the Components layer |
| Selection signal | `NSPopUpButton` target/action | `GtkDropDown` has no dedicated signal; `Bridge::connect('notify::selected')` | Detailed notify through the generic connect — Pi smoke still owed |
| Toggle statics | `state()` int 0/1 | `getActive()` bool | Surface stores bool; twins translate |
| Calendar | `NSDatePicker` (`CLOCK_AND_CALENDAR`, `YEAR_MONTH_DAY`, `SINGLE`); `dateValue()` is an `NSDate` handle — `NSDateFormatter` turns it into `Y-m-d` in the picker's default time zone | `GtkCalendar` ints; **month is 0-based**; `get_date`/`select_day` are `GDateTime*` and stay `@reserved` | Date-only graphical picker. Surface value is `Y-m-d`; twins speak ints. GTK twin adds 1 to month on the way out, subtracts 1 on the way in. AppKit twin formats through `NSDateFormatter` |
| Table | `NSTableView` in an `NSScrollView`; data source + delegate answer row count, cell views, and selection | `GtkColumnView` + `GtkSignalListItemFactory` per column; `GtkStringList` of row placeholders; `GtkListItem::getPosition` looks up `rows[pos][col]` in `bind`; `GtkSingleSelection` (`notify::selected`) | Read-only string cells, headers, single-row selection. `setRows()` replaces data and clears selection. Sorting and editing are follow-ups |

# Capability gaps to design around

Neither engine offers these through its 0.8.0 projection:

- **No flipped view on AppKit.** `isFlipped()` is a getter with no setter,
  and a PHP DTO cannot override an ObjC method. Top-left coordinates mean
  Surface flips y itself against the parent's height.
- **No stylesheet path on GTK.** CSS *class names* only — no
  `GtkCssProvider`, no `gtk_widget_apply_css`. AppKit's `NSView` has no
  `setBackgroundColor` either, and the `CALayer` route wants a `CGColor`
  while `NSColor` exposes no `CGColor()` getter. Background colour, text
  colour and font have no portable path today.
- **No signal muting on GTK.** Only connect and disconnect. An invariant
  like "programmatic setters never fire callbacks" has to be built from
  disconnect-set-reconnect or a PHP-side suppression flag.
- **No intrinsic sizing on AppKit views.** `fittingSize` and
  `intrinsicContentSize` are absent; `NSControl::sizeThatFits` covers
  controls only. There is no `NSLayoutConstraint` at all.
- **GTK callbacks deliver raw handles.** The caller boxes through the
  Registry. AppKit's bridge boxes DTOs for you.

# GPU regions (slice 2)

| Truth | AppKit (`NSOpenGLView`) | GTK (`GtkGLArea`) | Surface's pick |
|---|---|---|---|
| Who owns the GL context | the view's `NSOpenGLContext`; we make it current | GDK; current only inside `render` | host lends via `GLSurface`; engine never creates one |
| Target framebuffer | 0 (the window's back buffer) | GtkGLArea's own FBO, already bound | engine reads `DRAW_FRAMEBUFFER_BINDING` at `beginFrame()`; never assumes 0 |
| Dialect | GL 4.1 core → `#version 150 core` | GLES 3.1 → `#version 300 es` | chosen from `GL_VERSION` at init; `CORE_140` absent |
| Version gate | ext-opengl samples the desktop version | the gate cannot see GLES | GLES-safe intersection only, no desktop-only names |
| "No drawable" tick | none | none | `beginFrame()` always true on GL; only Metal's `nextDrawable` can say no |
| Present | `flushBuffer()` by the surface | GTK swaps on signal return; `present()` no-op | engine calls `present()`; the surface decides what that means |
| Who drives frames | the tick (no `drawRect:` for this) | GTK's frame clock via `render` | `drivesOwnFrames()`; tick queues for GTK |
| Drawable size | `convertRectToBacking(bounds)` | `getWidth/Height × getScaleFactor` | pixels, from the surface |
| Blending | on | on | `capabilities()->blending` true on OpenGL; Metal false until slice 5 |

[^jovian-appkit]: jovian/appkit 0.8.0, read at src/NS and src/Runtime
[^jovian-gtk]: jovian/gtk 0.8.0, read at src/Gtk and src/Runtime
