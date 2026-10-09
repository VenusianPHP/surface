---
okf_version: "0.2"
---

# venusian/surface

* [Components](architecture/components.md) - Splits (incl. nuts-and-bolts), providers, container bindings, published configs.

# Architecture

* [Bridge](architecture/bridge.md) - ToolkitManager picks a toolkit driver; its session starts the engine once, connects on demand, joins the loop as its sleeper.
* [Windows](architecture/windows.md) - Window kinds, the toolkit and staged window driver contracts, ToolkitWindowManager, StagedWindowManager.
* [Framebuffers](architecture/framebuffers.md) - Pixel storage behind a FormatSpec: five kinds over a pixel store, bytes in PHP (native) or C (extended, ext-fb); span and image painting.
* [Drawing](architecture/drawing.md) - Rendering engines: one draw API over a framebuffer, frames recorded then drawn, DrawingManager, VelvetGE the software engine.
* [Rasterize](architecture/rasterize.md) - Shapes into coverage spans, hard-edged or anti-aliased, geometry in PHP (native) or C (extended, ext-rasterize).
* [Images](architecture/images.md) - PNG, JPEG and TIFF bytes into full RGBA8 framebuffers; ext-gd + PHP (native) or C (extended, ext-imgdec).
* [Fonts](architecture/fonts.md) - Bitmap faces and their registry; text() on a rendering engine, onto any framebuffer.
* [EmbeddedDisplays](architecture/embedded-displays.md) - An IC panel as a drawing output beside TKCanvas: framebuffer in the panel's format, present() sends what changed.
* [Toolkit primitives](architecture/primitives.md) - Native widgets inside a ToolkitWindow: containers, registry, paths, placement, factory, apply hooks, engine callbacks.

# API

* [Window mail](api/mail.md) - Mail native callbacks post: window closed/focused/resized, menu activated/toggled, quit requested.
* [View mail](api/view-mail.md) - Mail primitive natives post: clicks, text, toggles, values, selections, dates, rows, resizes, video state.
* [Styling](api/styling.md) - Color (nuts-and-bolts) and the Windows typography values FontSpec, FontWeight, TextAlignment.
* [Menu profiles](api/menu-profiles.md) - config/windows.php menus: folders, items, roles, toggles, hotkeys, ids.
* [Config](api/config.md) - config/bridge.php, config/windows.php, config/framebuffers.php, config/rasterize.php, config/images.php, config/drawing.php and config/embedded-displays.php keys.

# Runbooks

* [Composing an app](runbooks/composing-an-app.md) - Require Surface + a toolkit driver, publish configs, sketch mail, boot/shutdown pattern.
* [Testing](runbooks/testing.md) - Pest suite with fakes in a workbench of path repos.
