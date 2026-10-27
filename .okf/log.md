# Log

## 2026-10-27

* Services, Surface layer: `AlertStyle`, `DialogCapability` (`AlertTitle`, `ShowHidden`) and `Dialogs::supports()`; `Alert` style, suppression and title; `OpenRequest` / `SaveRequest` filters and showHidden; `AlertAnswered::$suppressed`; `ToolkitDialogs` refuses a request that needs a capability the driver lacks and forgets a request whose show threw; `Clipboard::requestText()` / `hasText()`, `ToolkitClipboard` (one `ClipboardText` per id), `ClipboardChanged`. [windows](architecture/windows.md), [mail](api/mail.md)

## 2026-10-26

* Canvas keys, Surface layer: `EditCommand` (`Surface\Contracts\Windows\Input`, 40 cases: moves and their Shift selects, deletes, Yank, SelectAll, Cut, Copy, Paste, Undo, Redo, InsertNewline, InsertTab, InsertBacktab, Cancel) and the mail of a focused, focusable canvas: `KeyPressed`, `KeyReleased`, `TextCommitted`, `EditCommanded`. [view mail](api/view-mail.md)

## 2026-10-23

* Menus, Surface layer: radio items (`radio` group, `on`; `MenuItem::radio()`, `isOn()`; one folder, exactly one on, not an item id; `on` only on toggles and radios), `MenuRadioChosen`, `setRadio()` / `radio()` / `setItemLabel()` / `itemLabel()` on the window and, with `setToggle()` / `isToggled()`, the driver's default bar through `ToolkitWindowManager`; `MenuStates` keeps toggles, radios and labels (`toggleChanges()`, `radioChanges()`, `labelChanges()`; `changes()` stays enabled); context menus take toggles and radios and keep the user's choices (`withToggle()`, `withRadio()`, `nativeContextToggled()`, `nativeContextRadio()`, on menu buttons too); `TKPrimitive::popupMenu()` (`applyPopupMenu(): bool`, false when off screen); `TKTabView::addPage(…, ?index)`. [menu profiles](api/menu-profiles.md), [mail](api/mail.md), [primitives](architecture/primitives.md)
* Canvas scrolling, Surface layer: `TKScrollBar` (`scrollBar()`, `mintScrollBar()`; range, page, step, clamped value, `nativeMoved()`), `ScrollChanged`, hover mail (`CanvasMoved`, `CanvasEntered`, `CanvasLeft`), `ScrollPhase` on `CanvasWheeled` (default NONE); `Framebuffer::scroll()` on every kind (paged refuses), `RenderingEngine::canScroll()` / `scroll()` with the scrolled region in the next frame's damage. [primitives](architecture/primitives.md), [view mail](api/view-mail.md), [drawing](architecture/drawing.md), [framebuffers](architecture/framebuffers.md)
* Path bar and toolbar, Surface layer: `TKComboBox` (options, a completion list under the field, cleared when hidden), `TKMenuButton`, button images (`HasButtonImage`: `setImage(?Framebuffer, ImagePosition, scale)`, `setBordered`) on push and toggle buttons; `comboBox()`, `menuButton()`, `mintComboBox`, `mintMenuButton`. [primitives](architecture/primitives.md)

* `TKPrimitive::focus()` refuses a primitive under a hidden container ("not shown") and one disabled itself or through a container ("cannot take focus"), without asking the native: a toolkit would focus a widget the user cannot see or use. [primitives](architecture/primitives.md)

## 2026-10-22

* Focus and window keys, Surface layer: `TKPrimitive::focus()` / `hasFocus()`, `TKCanvas::setFocusable($on, $takesTab)` / `isFocusable()` / `takesTab()`, `ToolkitWindow::focused()` / `setInitialFocus()`; `FocusGained`, `FocusLost`, `WindowKeyPressed`; the key precedence. [primitives](architecture/primitives.md), [windows](architecture/windows.md), [view mail](api/view-mail.md), [mail](api/mail.md)

## 2026-10-21

* Dialogs, clipboard and desktop, Surface layer: `Dialogs`, `Clipboard`, `Desktop`, `Alert`, `OpenRequest`, `SaveRequest`; `AlertAnswered`, `FilesChosen`; `ToolkitDialogs` (request ids, one modal per parent, parent-close answers); `dialogs()` / `clipboard()` / `desktop()` on drivers and `ToolkitWindowManager`. [windows](architecture/windows.md), [mail](api/mail.md)

## 2026-10-20

* Modal children, close veto and button roles, Surface layer: `ToolkitWindowDriver::open()` and `ToolkitWindowManager::open()` take a parent; `ToolkitWindow::parent()`, `confirmClose()`, `isConfirmingClose()`; `ChildWindows` (parent rules, `childOf`); `ButtonRole`, `TKButton::setRole()` / `role()` / `holder()`. [windows](architecture/windows.md)
* Menu enabled state and accelerators, Surface layer: `Accelerator`, `MenuStates`, node keys `keys` and `enabled`, `hotkey` limited to accelerator characters, id and accelerator collisions refused, `setItemEnabled()` / `isItemEnabled()` on windows, drivers and `ToolkitWindowManager`. [menu profiles](api/menu-profiles.md)

## 2026-10-19

* Canvas pointer, Surface layer: `TKCanvas::watchPointer()` / `isWatchingPointer()` (abstract flag, nothing written to the native, `WindowException` once removed); `CanvasPressed`, `CanvasDragged`, `CanvasReleased`, `CanvasWheeled` with the press and wheel rules. [primitives](architecture/primitives.md), [view mail](api/view-mail.md)

## 2026-10-18

* Tree: `TKTree` over `TreeNode`s (cells and colours per column, children), expansion record kept across collapse and `setNodes`; `NodeSelected`, `NodeActivated`, `NodeExpanded`. [primitives](architecture/primitives.md), [view mail](api/view-mail.md)

## 2026-10-16

* Split and tabs: `TKSplitView` (panes, divider positions as fractions of visible panes) and `TKTabView` (pages as columns, titles, selection); `SplitMoved`, `TabSelected`. [primitives](architecture/primitives.md), [view mail](api/view-mail.md)

## 2026-10-14

* Text panes: TKTextArea read-only, wrap, line backgrounds, range styles, line selection, scroll position and ViewScrolled; FontSpec monospace; TextLines for UTF-16 drivers. [primitives](architecture/primitives.md)
* Releasing: `split.yml` on a `v*` tag; new `tests.yml` (PHP 8.4/8.5, ext-gd) gates; `.github/split.sh` splits all eleven components to `venusian-surface` with the same tag. [releasing](runbooks/releasing.md)

## 2026-10-10

* `TKLink`: `link(name, text, url)`, a link the toolkit opens; URL needs a scheme; posts nothing (toolkit links). [primitives](architecture/primitives.md)
* Edit menu roles: undo, redo, cut, copy, paste, select-all; no hotkey on them; Surface's default main profile has an Edit folder (HumanInput slice 29). [menu profiles](api/menu-profiles.md)

## 2026-10-09

* `View\CanvasClicked`: a click on a canvas posts `view.clicked` with the point (HumanInput slice 28). [view mail](api/view-mail.md)
* Context menus: `TKPrimitive::setContextMenu()` / `contextMenu()`, `ContextMenu`; a chosen item posts `MenuActivated` (HumanInput slice 27). [menu profiles](api/menu-profiles.md)
* `View\RightClicked` and `WindowRightClicked`: right-click mail for toolkit windows (HumanInput slice 26). [view mail](api/view-mail.md), [mail](api/mail.md)

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
