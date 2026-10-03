---
okf_version: "0.2"
---

# venusian/surface

* [Components](architecture/components.md) - Splits (incl. nuts-and-bolts), providers, container bindings, published configs.

# Architecture

* [Bridge](architecture/bridge.md) - ToolkitManager picks a toolkit driver; its session starts the engine once, connects on demand, joins the loop as its sleeper.
* [Windows](architecture/windows.md) - Window kinds, the toolkit window driver contract, ToolkitWindowManager.
* [Toolkit primitives](architecture/primitives.md) - Native widgets inside a ToolkitWindow: containers, registry, paths, placement, factory, apply hooks, engine callbacks.

# API

* [Window mail](api/mail.md) - Mail native callbacks post: window closed/focused/resized, menu activated/toggled, quit requested.
* [View mail](api/view-mail.md) - Mail primitive natives post: clicks, text, toggles, values, selections, dates, rows, resizes, video state.
* [Styling](api/styling.md) - Color (nuts-and-bolts) and the Windows typography values FontSpec, FontWeight, TextAlignment.
* [Menu profiles](api/menu-profiles.md) - config/windows.php menus: folders, items, roles, toggles, hotkeys, ids.
* [Config](api/config.md) - config/bridge.php and config/windows.php keys.

# Runbooks

* [Composing an app](runbooks/composing-an-app.md) - Require Surface + a toolkit driver, publish configs, sketch mail, boot/shutdown pattern.
* [Testing](runbooks/testing.md) - Pest suite with fakes in a workbench of path repos.
