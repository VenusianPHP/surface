---
type: Reference
title: View mail
description: Mail primitive natives post - clicks, text, toggles, values, selections, dates, rows, resizes, video state.
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
| `TextChanged` | value | `view.text-changed.<window>.<path>` | `TKTextInput`, `TKTextArea` |
| `TextSubmitted` | value | `view.text-submitted.<window>.<path>` | `TKTextInput` (return) |
| `Toggled` | on | `view.toggled.<window>.<path>` | `TKCheckbox`, `TKToggle`, `TKToggleButton` |
| `ValueChanged` | float value | `view.value-changed.<window>.<path>` | `TKSlider` |
| `SelectionChanged` | int index, ?option | `view.selection-changed.<window>.<path>` | `TKDropdown` (-1 / null = no options) |
| `DateChanged` | DateTimeImmutable date | `view.date-changed.<window>.<path>` | `TKDatepicker` |
| `RowSelected` | ?int row, ?array cells (column id => text) | `view.row-selected.<window>.<path>` | `TKTable` (null = cleared) |
| `ViewResized` | width, height | `view.resized.<window>.<path>` | any primitive after `watchSize()` |
| `VideoPlaying` / `VideoPaused` / `VideoEnded` | — | `view.video-playing.<window>.<path>` etc. | `TKVideo` |
| `VideoFailed` | reason | `view.video-failed.<window>.<path>` | `TKVideo` (bad file, codec) |

Window-level: `WindowResized(window, width, height)` → `window.resized.<window>` ([window mail](/api/mail.md)).

# Rules

* User action only: changing state from code posts nothing.
* Driver posts right after the abstract's `native*()` callback records the state.
* `ViewResized` and `WindowResized` go through `postLatest()` keyed `view.resized.<window>.<path>` / `window.resized.<window>`: one mail per target per pump, last size wins.
* Removed view: `remove()` switches watching off before the native dies; the driver's `applyWatchSize(false)` calls `forgetLatest()` on the view's key, so a pending `ViewResized` never goes out. Window close forgets `window.resized.<window>` before posting `WindowClosed`.

[^mail]: View mail classes
