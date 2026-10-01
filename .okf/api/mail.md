---
type: Reference
title: Window mail
description: Mail native callbacks post - window closed/focused, menu activated/toggled, quit requested.
resource: src/Surface/Contracts/Windows/Mail/
tags: [surface, mail, signals]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-01T20:04:54Z }
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
| `MenuActivated` | window, item | `menu.activated.<window>.<item>` | plain item chosen |
| `MenuToggled` | window, item, on | `menu.toggled.<window>.<item>` | toggle chosen by the user; `on` = new state |
| `QuitRequested` | ?window | `quit.requested` | Quit item chosen |

* Default bar items (macOS, no window) post `window` = `''`; Quit posts `window` = `null`.
* `setToggle()` from code posts nothing.
* About posts nothing: the driver shows a non-modal About itself.
* `QuitRequested` quits nothing: the handler closes windows and stops the loop.

[^mail]: Mail classes
