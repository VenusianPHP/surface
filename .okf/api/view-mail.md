---
type: Reference
title: View mail
description: Mail primitive natives post - clicks, canvas pointer and wheel, text, toggles, values, selections, dates, rows, resizes, video state.
resource: src/Surface/Contracts/Windows/Mail/View/
tags: [surface, mail, signals, primitives]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-02T19:45:27Z }
sources:
  - id: mail
    resource: src/Surface/Contracts/Windows/Mail/View/
    title: View mail classes
---

# Schema

All readonly `NamedSignal` + `PrimitiveMail` (`window()`, `path()`, `uuid()`); base `PrimitiveEventOccurred(window, path, uuid)`. `<path>` = dotted [primitive](/architecture/primitives.md) path.[^mail]

| Class | Extra fields | `name()` | From |
|---|---|---|---|
| `ButtonClicked` | — | `view.clicked.<window>.<path>` | `TKButton` |
| `CanvasClicked` | x, y (where the press landed, points from the canvas's top-left) | `view.clicked.<window>.<path>` | canvas: primary press + release on it, not moved past the drag distance (a tap too); not Ctrl-click on macOS, not a hold past the long-press time on Linux (right clicks); none when it or an ancestor is disabled |
| `CanvasPressed` | x, y, `MouseButton` button, int clicks, `Modifiers` modifiers | `view.pressed.<window>.<path>` | canvas after `watchPointer()`: a button down on it, which then owns the press (other buttons post nothing until it ends); clicks counts same-button presses within the toolkit's double-click time and distance, so a triple click is 3 |
| `CanvasDragged` | x, y, button, modifiers | `view.dragged.<window>.<path>` | the owning button held and the point changed; keeps posting outside the canvas and the window (implicit capture), x / y then negative or past its size |
| `CanvasReleased` | x, y, button, modifiers | `view.released.<window>.<path>` | exactly one per `CanvasPressed`: the owning button's release, or at once at the last point posted when the toolkit ends the press (context menu, GTK grab broken, touch cancelled) |
| `CanvasWheeled` | dx, dy, bool precise, modifiers, `ScrollPhase` phase (NONE) | `view.wheeled.<window>.<path>` | a wheel over a watching, enabled canvas; kept from an enclosing scroll view. precise = trackpad points, else notches (one notch = 1.0); positive dx / dy go toward the content's right / bottom. phase: NONE for a notch or a device without phases; BEGAN, CHANGED…, ENDED for fingers (a cancelled gesture ENDED); MOMENTUM… then MOMENTUM_ENDED for the coast (deltas may be 0); phases without movement post nothing |
| `CanvasMoved` | x, y, modifiers | `view.moved.<window>.<path>` | latest-wins; the pointer moved over a watching, enabled canvas with no button held (held: `CanvasDragged`) |
| `CanvasEntered` | — | `view.entered.<window>.<path>` | the pointer crossed into a watching, enabled canvas; none during an owned press |
| `CanvasLeft` | — | `view.left.<window>.<path>` | the pointer crossed out or left the window; none during an owned press; a press released outside posts it after `CanvasReleased`; a removed canvas posts nothing |
| `ScrollChanged` | int value | `view.scroll-changed.<window>.<path>` | latest-wins; `TKScrollBar`: the user dragged the thumb (live), clicked the track, or wheeled over it |
| `FocusGained` | — | `view.focus-gained.<window>.<path>` | any primitive: the user moved keyboard focus to its native (click, Tab, the toolkit's own move); never from `focus()` or `setInitialFocus()` |
| `FocusLost` | — | `view.focus-lost.<window>.<path>` | any primitive: the user moved keyboard focus away from its native |
| `RightClicked` | x, y (in the primitive) | `view.right-clicked.<window>.<path>` | any primitive: the innermost under a right click (secondary click, Ctrl-click on macOS, a primary press held still on Linux); none when it or an ancestor is disabled |
| `TextChanged` | value | `view.text-changed.<window>.<path>` | `TKTextInput`, `TKTextArea` |
| `TextSubmitted` | value | `view.text-submitted.<window>.<path>` | `TKTextInput` (return) |
| `Toggled` | on | `view.toggled.<window>.<path>` | `TKCheckbox`, `TKToggle`, `TKToggleButton` |
| `ValueChanged` | float value | `view.value-changed.<window>.<path>` | `TKSlider` |
| `SelectionChanged` | int index, ?option | `view.selection-changed.<window>.<path>` | `TKDropdown` (-1 / null = no options) |
| `DateChanged` | DateTimeImmutable date | `view.date-changed.<window>.<path>` | `TKDatepicker` |
| `RowSelected` | ?int row, ?array cells (column id => text) | `view.row-selected.<window>.<path>` | `TKTable` (null = cleared) |
| `ViewResized` | width, height | `view.resized.<window>.<path>` | any primitive after `watchSize()` |
| `ViewScrolled` | position (ScrollPosition) | `view.scrolled.<window>.<path>` | `TKTextArea` while watched; latest-only |
| `SplitMoved` | positions (list<float>) | `view.split-moved.<window>.<path>` | `TKSplitView`: once a user divider drag ends with positions changed; not window resizes or `setPositions()` |
| `TabSelected` | page (name) | `view.tab-selected.<window>.<path>` | `TKTabView`: user tab switch; not `select()`, `addPage()` or removing a page |
| `NodeSelected` | ?id | `view.node-selected.<window>.<path>` | `TKTree`: user selection (null = cleared); not `selectNode()` or `setNodes()` |
| `NodeActivated` | id | `view.node-activated.<window>.<path>` | `TKTree`: double click, Enter or Return |
| `NodeExpanded` | id, expanded | `view.node-expanded.<window>.<path>` | `TKTree`: user expand / collapse; not `expand()`, `selectNode()` or `setNodes()` |
| `VideoPlaying` / `VideoPaused` / `VideoEnded` | — | `view.video-playing.<window>.<path>` etc. | `TKVideo` |
| `VideoFailed` | reason | `view.video-failed.<window>.<path>` | `TKVideo` (bad file, codec) |

Window-level: `WindowResized(window, width, height)` → `window.resized.<window>` ([window mail](/api/mail.md)).

# Rules

* User action only: changing state from code posts nothing.
* Driver posts right after the abstract's `native*()` callback records the state.
* `ViewResized` and `WindowResized` go through `postLatest()` keyed `view.resized.<window>.<path>` / `window.resized.<window>`: one mail per target per pump, last size wins.
* Removed view: `remove()` switches watching off before the native dies; the driver's `applyWatchSize(false)` calls `forgetLatest()` on the view's key, so a pending `ViewResized` never goes out. Window close forgets `window.resized.<window>` before posting `WindowClosed`.

[^mail]: View mail classes
