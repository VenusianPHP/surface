---
type: Reference
title: Window mail
description: Mail native callbacks post - window closed/focused/resized, menu activated/toggled, quit requested.
resource: src/Surface/Contracts/Windows/Mail/
tags: [surface, mail, signals]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-02T19:45:27Z }
sources:
  - id: mail
    resource: src/Surface/Contracts/Windows/Mail/
    title: Mail classes
---

# Schema

All readonly, all `Voyager\Contracts\Signals\NamedSignal` (`name()` for Signals wildcards). Window mail implements `WindowMail` (`window()`), base `WindowEventOccurred`.[^mail]

| Class | Fields | `name()` | Posted when |
|---|---|---|---|
| `WindowClosed` | window | `window.closed.<window>` | window closed (button or `close()`), once |
| `WindowFocused` | window | `window.focused.<window>` | window became active / key |
| `WindowRightClicked` | window, x, y | `window.right-clicked.<window>` | right click on the window's bare content (no primitive under it); x, y in the content area |
| `WindowKeyPressed` | window, `Key` key, character, `Modifiers` modifiers, repeat | `window.key.<window>` | a key press no focused native, menu accelerator, default/cancel button or Tab move took; key is physical, character the layout's text (`''` for none) |
| `MenuActivated` | window, item | `menu.activated.<window>.<item>` | plain item chosen |
| `MenuToggled` | window, item, on | `menu.toggled.<window>.<item>` | toggle chosen by the user; `on` = new state |
| `MenuRadioChosen` | window, group, item | `menu.radio.<window>.<group>` | the user chose another item of a radio group, bar or context menu |
| `QuitRequested` | ?window | `quit.requested` | Quit item chosen |
| `WindowResized` | window, width, height | `window.resized.<window>` | content area resized; via `postLatest`, one per pump, last size |
| `Services\AlertAnswered` | id, button | `dialog.alert.<id>` | alert answered: button index; Esc and the alert's close give its cancel index; `-1` when the parent closed first |
| `Services\FilesChosen` | id, paths | `dialog.files.<id>` | file panel answered: absolute paths; `[]` when cancelled, dismissed or the parent closed first |

* Default bar items (macOS, no window) post `window` = `''`; Quit posts `window` = `null`.
* `setToggle()` from code posts nothing.
* About posts nothing: the driver shows a non-modal About itself.
* `QuitRequested` quits nothing: the handler closes windows and stops the loop.
* Primitive mail: [view mail](/api/view-mail.md).

[^mail]: Mail classes
