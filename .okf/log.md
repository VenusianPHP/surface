# Log

## 2026-10-08

* Staged windows phase 5, staged frames: [drawing](architecture/drawing.md) gains vsync, HDR targets, pacing, fixed resolution and `DamageHistory` on GPU engines; [framebuffers](architecture/framebuffers.md) gains `HdrImage` and `HdrReadback`; [windows](architecture/windows.md) gains scaling and HDR on lent surfaces; [testing](runbooks/testing.md) names the staged GPU fakes.
* `HostsDrawing::applyPixels()` takes the damage list like `applyAddress()`: a PHP-held framebuffer's damage reaches the host.
* Staged windows grow what a game engine asks of its window: `WindowMode` incl. exclusive fullscreen at a `DisplayMode`, `Display` and display modes, position, limits, aspect, safe area, style toggles, `WindowCapability` per backend, vsync carried to `LentSurface`, scaling fit and filter with `presentRect()`, keep-awake, attention, icon, hit tests, HDR, and ten mail types incl. `confirm_close`. `fullscreen(bool)` and the `fullscreen` option replaced by `setMode()` and `mode`. [windows](architecture/windows.md).

## 2026-10-05

* GPU engine contracts: [drawing](architecture/drawing.md) gains GPU engines, the draw list and the manager's shared arguments; [framebuffers](architecture/framebuffers.md) gains GLFramebuffer; [primitives](architecture/primitives.md) gains surface lending on the canvas; [embedded displays](architecture/embedded-displays.md) gains output size and format and the staging copy; [testing](runbooks/testing.md) names the GPU fakes and the parity suite.

## 2026-10-04

* Slice 0, Tasks 5 and 11: `renderer('velvet', ['output' => $target])` draws over a target's `framebuffer()`; `keepsFrame()` keeps a ring's frame for every engine. Added `DirectEDisplay` (`Pipeable`): regions piped from ext-fb memory through a gpio/contracts `PipeablePanel`'s `WritesFromMemory` bus; `attach()` / `panel()` take `direct: true`. Measured on the Pi 5's ST7796. [embedded-displays](architecture/embedded-displays.md), [drawing](architecture/drawing.md).
* Slice 0, Task 4: `Output` renamed `OutputTarget` and gains `framebuffer()`; added `Pipeable` (`canPipe()`); `TKCanvas` is pipeable — an extended RGBA8 framebuffer reaches the toolkit by address (`applyAddress`) with its damage, default kind `dirty`. [primitives](architecture/primitives.md), [embedded-displays](architecture/embedded-displays.md).
* EmbeddedDisplays on 0.10: added [embedded-displays](architecture/embedded-displays.md); `Output` shared with `TKCanvas`; `app('displays')`, `config/embedded-displays.php`. [drawing](architecture/drawing.md) gains partial frames, `damage()`, `invalidate()`, the region `clear`. [components](architecture/components.md), [config](api/config.md).
* Fonts ported to 0.10: added [fonts](architecture/fonts.md); `text()` / `textBounds()` on `RenderingEngine`, the `spans` command; `app('fonts')`, `config/fonts.php`, `make:font`. [drawing](architecture/drawing.md), [components](architecture/components.md).
* Framebuffers, Rasterize, Images: `auto` driver, the new config default (`FRAMEBUFFERS_DRIVER`, `RASTERIZE_DRIVER`, `IMAGES_DRIVER`), extended when the extension is loaded, native when not. [config](api/config.md), [components](architecture/components.md).
* Surface 0.10 phase 4, Images: added [images](architecture/images.md); [framebuffers](architecture/framebuffers.md) gains `writeRgba8()`; [components](architecture/components.md), [config](api/config.md) and [testing](runbooks/testing.md) gain the split, its config and its tests.

## 2026-10-03

* Surface 0.10 phase 4, canvas: [toolkit primitives](architecture/primitives.md) gains `TKCanvas` (framebuffer + present); [components](architecture/components.md): Windows now requires Framebuffers.
* Surface 0.10 phase 4, Drawing: added [drawing](architecture/drawing.md); [framebuffers](architecture/framebuffers.md) gains image painting; [components](architecture/components.md), [config](api/config.md) and [testing](runbooks/testing.md) gain the split, `Affine`, its config and its tests.
* Surface 0.10 phase 4, Rasterize: added [rasterize](architecture/rasterize.md); [framebuffers](architecture/framebuffers.md) gains span painting; [components](architecture/components.md), [config](api/config.md) and [testing](runbooks/testing.md) gain the split, its config and its tests.
* Surface 0.10 phase 4, Framebuffers: added [framebuffers](architecture/framebuffers.md); [components](architecture/components.md), [config](api/config.md) and [testing](runbooks/testing.md) gain the split, its config and its tests.

## 2026-10-02

* Surface 0.10 slice 3 (toolkit primitives), Surface layer: added [toolkit primitives](architecture/primitives.md), [view mail](api/view-mail.md), [styling](api/styling.md); [components](architecture/components.md) gains the `venusian-surface/nuts-and-bolts` split; [windows](architecture/windows.md) gains the content container and lookups; [window mail](api/mail.md) gains `WindowResized`; [bridge](architecture/bridge.md) gains `postLatest`/`flushLatest`; [testing](runbooks/testing.md) names the primitive fakes. Review fixes the same day: `Placement`/`PrimitiveRegistry` in contracts, reorder on the child, `TKFixed::frameOf()`, `forgetLatest()`, guarded forgets, finite-float and trailing-newline guards.

## 2026-10-01

* Bundle created with Surface 0.10 slices 1-2 (bridge, toolkit windows): [components](architecture/components.md), [bridge](architecture/bridge.md), [windows](architecture/windows.md), [mail](api/mail.md), [menu profiles](api/menu-profiles.md), [config](api/config.md), [composing an app](runbooks/composing-an-app.md), [testing](runbooks/testing.md).
