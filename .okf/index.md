---
okf_version: "0.2"
---

# venusian/surface

* [Components](architecture/components.md) - Splits, providers, container bindings, published configs.

# Architecture

* [Bridge](architecture/bridge.md) - ToolkitManager picks a toolkit driver; its session starts the engine once, connects on demand, joins the loop as its sleeper.
* [Windows](architecture/windows.md) - Window kinds, the toolkit window driver contract, ToolkitWindowManager.

# API

* [Window mail](api/mail.md) - Mail native callbacks post: window closed/focused, menu activated/toggled, quit requested.
* [Menu profiles](api/menu-profiles.md) - config/windows.php menus: folders, items, roles, toggles, hotkeys, ids.
* [Config](api/config.md) - config/bridge.php and config/windows.php keys.

# Runbooks

* [Composing an app](runbooks/composing-an-app.md) - Require Surface + a toolkit driver, publish configs, sketch mail, boot/shutdown pattern.
* [Testing](runbooks/testing.md) - Pest suite with fakes in a workbench of path repos.
