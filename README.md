# venusian/surface

Native windows, GPU drawing, and human input for the Venusian framework. One
PHP vocabulary, translated by the AppKit, GTK4, and SDL3 engines. Your code
owns the loop; the OS does not.

## Human input

A sketch reads input state each tick, from an OS engine or an IC, through
one vocabulary. It never learns which is plugged in.

```php
$snes = new SNESClassicController(new WiiConnectorI2CTransport($slave), boot_now: true);
HumanInput::attach($snes, 'p1');                        // IC → GamePad

// loop
LiveApp::get()->tick(16);
$kb = HumanInput::keyboard();                            // default engine, config: sdl3
$pad = HumanInput::gamePads()['p1'];
if ($kb->isPressed(Key::SPACE) || $pad->isPressed(GamepadButton::SOUTH)) { $player->jump(); }
$stick = array_values(HumanInput::gameControllers())[0] ?? null;   // first controller, if any (keyed by device id)
```

| Engine | Keyboard / mouse | Gamepads |
|---|---|---|
| `sdl3` (default) | SDL stage windows | SDL gamepads, every OS |
| `appkit` | native macOS windows | GameController.framework |
| `gtk` | native Linux windows | evdev |

Circuits — seesaw, Wii Classic/Nunchuck, SNES/NES Classic Mini, or a Linux
evdev pad wrapped directly — attach with `HumanInput::attach($ic, $name)` and
read back exactly like an engine's gamepad. Surface never imports GPIO code:
a circuit crosses the seam only by implementing
`Surface\Contracts\HumanInput\Circuits\ButtonPad` or `GameController`.
