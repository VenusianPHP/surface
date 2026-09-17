---
type: Architecture
title: HumanInput — keyboards, mice, pads
description: >-
  Keyboards, mice, game pads and game controllers behind one vocabulary,
  sourced from an input.<engine> package or an attached IC circuit, polled on
  the IOPool dock's `input` resource.
tags: [surface, human-input, gamepad, keyboard, mouse, io-pools]
status: draft
generated: { by: claude-sonnet/claude-code, at: "2026-09-17T04:37:15Z" }
revised: { by: claude-opus-5/claude-code, at: "2026-09-17T12:00:00Z", note: "focus loss, window(), wheel units, circuit sub-tick taps, faults, circuit sleeps, who-sees-what" }
sources:
  - id: manager
    resource: src/Surface/HumanInput/HumanInputManager.php
    title: HumanInputManager
  - id: resource
    resource: src/Surface/HumanInput/HumanInputResourceDriver.php
    title: HumanInputResourceDriver — the dock's input resource
  - id: ic
    resource: src/Surface/HumanInput/ICInput.php
    title: ICInput
  - id: button
    resource: src/Surface/HumanInput/Devices/DigitalButton.php
    title: DigitalButton
  - id: contracts
    resource: src/Surface/Contracts/HumanInput
    title: HumanInput contracts
  - id: config
    resource: config/human-input.php
    title: human-input config
  - id: tests
    resource: tests/HumanInput
    title: HumanInput tests
---

# Shape

Same seam as [GPU and Stage](/engine-seam.md): Surface owns vocabulary +
device model, sources translate, a container alias names the source.

```
input.sdl3    jovian/venusian-sdl3    ┐
input.appkit  jovian/venusian-appkit  ├─ InputEngineDriver ─┐
input.gtk     jovian/venusian-gtk     ┘                     ├─ HumanInputManager ── `input` dock resource
seesaw, wii-* dept-of-scrapyard-robotics                    │    keyboard() mouse() gamePads() gameControllers()
  implement Surface\Contracts\HumanInput\Circuits\{ButtonPad,GameController}   engine(name) attach(ic,name) detach(name)
```

`HumanInput::` (MagicAlias, accessor `human-input`) exposes
`keyboard() mouse() gamePads() gameControllers() engine(?name) engines() attach(ic,name) detach(name) circuits()`.
Default engine `sdl3`, overridden by `INPUT_ENGINE` env (`config/human-input.php`,
same shape as `config/gpu.php`); `human-input.engines.<name>.alias` rebinds an
engine's container alias without code.[^manager][^config]

# Rules

- Engine start is lazy: the first `engine($name)` call resolves the alias and
  calls `connect()`; the manager caches the connected driver.[^manager]
- The `input` resource never starts an engine. `HumanInputResourceDriver::tick()`
  only polls engines the manager already connected and circuits already
  attached — a sketch that reads no input pays nothing.[^resource]
- `keyboard()` / `mouse()` read the **default engine only**; there is no
  merge across engines for these two.[^manager]
- `gamePads()` / `gameControllers()` merge every connected engine's devices
  with every attached circuit. Engines are folded in first (`+=`), circuits
  second by plain key assignment — so **a circuit whose `attach()` name equals
  an engine device id replaces that device** in the returned list.[^manager]
- Edge rule: `settle()` clears `pressed`/`released` (and mouse motion/wheel,
  keyboard text); `update(down)` sets `pressed` on a false→true change and
  `released` on true→false, ORing within the tick. A source's `poll()` is
  always settle-every-device then apply-this-tick's-state. A tap that starts
  and ends inside one poll shows `isPressed()` **and** `wasReleased()` both
  true — taps shorter than a tick are not lost.[^button]
- Focus loss: on deactivate an engine releases every key and mouse button.
- `Mouse::window()` = name of the window under the pointer, or null.
  `wheel()` is in lines, `dy > 0` = wheel rolled away from the user
  (physical, not content direction); engines flip to match.
- Axis writes clamp on the way in: sticks −1…1, triggers 0…1; an axis outside
  the device's built set is ignored, never thrown.
- GamePad vs GameController is decided once, by axis set: a device is a
  `GameController` when its axes contain `LEFT_X` or `RIGHT_X`, else a plain
  (digital-only) `GamePad`. `ICInput` makes this call at construction from
  `Circuits\GameController::supportedAxes()`.[^ic]
- A disconnected circuit is not polled, reads all-released, and drops out of
  `gamePads()`/`gameControllers()` — but stays attached. `ICInput::poll()`
  always `settle()`s the device; it calls the circuit's own `poll()` and
  reads `isDown()`/`isPressed()`/`wasReleased()`/`axis()` only when
  `connected()` is true, otherwise every button updates false and every axis
  reads 0.0. Only an explicit `detach()` removes it.[^ic]
- Sub-tick taps from circuits: per button, circuit `isPressed()` and not
  `isDown()` → device `update(true)` then `update(false)`; device was down,
  circuit `wasReleased()` and `isDown()` → `update(false)` then
  `update(true)`; else `update(isDown())`.[^ic]
- Circuit fault: a `\Throwable` from the circuit's `connected()`, `poll()`
  or reads is caught. `ICInput` latches `faulted()` / `fault()`, releases
  every button, zeroes axes, never polls the circuit again, and answers
  `connected()` false — so the manager drops it and `input` mails
  `input.gamepad.disconnected.<name>` once. Nothing escapes
  `ICInput::poll()` or `tick()`. Recovery: `detach()` + `attach()`.[^ic]
- `tick()` never waits on its own, but an attached I2C circuit's `poll()`
  sleeps (wii `read_delay_us`, 3 ms default; seesaw ≈ 1.25 ms per poll).
- A circuit attached before it has booted reads disconnected (IC
  `connected()` = booted) and is never polled — boot first
  (`boot_now: true`).[^resource]
- Mail: `input.gamepad.connected.<id>` / `input.gamepad.disconnected.<id>`,
  one name per event class (`Events\GamepadConnected` /
  `GamepadDisconnected`). Computed each tick by diffing this tick's
  `id => name` set (`gamePads() + gameControllers()`) against the last. No
  per-key or per-button mail — a sketch polls state.[^resource]
- `HumanInputServiceProvider::boot()` registers `input` on the dock inside
  `Application::booted()`, which fires after every provider's `boot()` — so
  `input` lands on the dock **after `os`**, and native events `os` pumped
  this tick are already visible to `input` the same tick.
- `LiveApplication::destroy()` order: input engines, then stages, then
  windows, then the bridge (`HumanInputManager::destroy()` disconnects every
  connected engine, first failure remembered and rethrown after the rest
  run). A throw at any step still runs the remaining steps.

# Who sees what

Engine packages live in `jovian/venusian-*`, not this repo.

| Engine | Keyboard / mouse scope | Gamepad source |
|---|---|---|
| `sdl3` | SDL stages (`stage.sdl3` windows) | SDL gamepads (`SDLGetGamepads`/`SDLGamepadButton`/`Axis`); pads need no window |
| `appkit` | native windows + appkit stages | GameController.framework (`GCController` extended-gamepad profile) |
| `gtk` | native windows; natural scroll not detectable on GTK 4.8 | evdev (`/dev/input/event*` via `microscrap/scrapyard-evdev`) |

Keyboard and mouse belong to whoever owns the window: an engine sees only the
windows its own package provisions.

# Circuits

An IC (or an evdev pad) implements `Surface\Contracts\HumanInput\Circuits\ButtonPad`
or `\Circuits\GameController` — never the read-side `Devices\*` interfaces a
sketch reads. Implementers widen constructor params with their own chip
enums (button/axis naming stays chip-native); `ICInput` is what maps a
circuit's reports onto the shared `GamepadButton`/`GamepadAxis` vocabulary
and derives edges.[^ic]

Surface imports no GPIO code — IC packages depend on `surface/contracts`
only, the `ssd1306` precedent. Circuit polling rides the `input` dock
resource (`HumanInputResourceDriver::tick()` polls every attached
`ICInput`), not a GPIO dock; a circuit keeps its own `every($gpio)` helper
for use outside Surface.

# Proven on

| Box | Engine | Target | Devices | Pass |
|---|---|---|---|---|
| Mac (M-series, Herd PHP 8.4) | sdl3 | SDL stage | built-in keyboard, trackpad, Backbone pad ("PS4 Controller") | keys, text, mouse, wheel, pad connect/disconnect, buttons, stick, trigger |
| Mac | appkit | native window | keyboard, trackpad, Backbone pad (`GCDualShockGamepad`, `gc-*`) | keys, text, repeat, Cmd-Tab releases held keys, no beep, mouse, wheel, pad |
| Pi 5 (labwc, PHP 8.4 ZTS) | gtk | native window | USB keyboard, USB mouse, BT DualSense (evdev `event7`) | keys, Shift, focus-loss release, mouse, wheel, taps, stick, trigger, BT off/on mail |
| Pi 5 | sdl3 | SDL stage (`SDL_VIDEODRIVER=x11`) | same | same as gtk; pad `sdl3-*` |
| Pi 5 | none | circuits | seesaw mini gamepad 0x50 (I2C bus 1) | buttons by position (A → east), taps, stick −1…1 both axes |

Owed: SNES Classic Mini (0x52) on hardware. The DualSense motion-sensor
node is not a pad (no `BTN_SOUTH`). On the Mac, SDL keys need the stage to
own the NSApp pump ([stage](/stage.md)).

# Not here

Touch, rumble, IME, per-key mail, action maps.

[^manager]: HumanInputManager
[^resource]: HumanInputResourceDriver — the dock's input resource
[^ic]: ICInput
[^button]: DigitalButton
[^config]: human-input config
