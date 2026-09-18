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

## CPU rendering

Sketch draws with one hook on CPU or GPU. CPU path: the engine owns a
framebuffer in the panel's own byte format. The sketch never touches it.

```php
$canvas = CPU::driver('dirty')->attach(new CPUHost(128, 64, $ssd1306->formatSpec()));
$canvas->onDraw(fn (Drawing2D $g, Frame $f) => $g->fillCircle(64, 32, 20, Color::hex('#fff')));

$canvas->renderFrame();
foreach ($canvas->damage() as $region) {
    $ssd1306->transmit($region->x, $region->y, $canvas->flushRegion($region, as_array: true), $region->width, $region->height);
}

$gpu->drawing()->texture($canvas->rgba8(), 128, 64);   // same pixels onto any GPU engine
```

| Engine | Canvas | What it owns |
|---|---|---|
| `dirty` (default) | `DirtyCanvas` | damage regions, snapped; fill once at attach |
| `full` | `FullCanvas` | whole surface every frame |
| `epaper` | `EPaperCanvas` | whole surface; paper is the default clear |
| `paged` | `PagedCanvas` | one page of RAM, hook per page, `onPage` streams |
| `nframes` | `NFramesCanvas` | ring; writes the back, flips on present |

`CPU_ENGINE` picks the default (`dirty`). Bytes live behind
`FRAMEBUFFER_DRIVER` (`php` in-house, always available). Set
`FRAMEBUFFER_DRIVER=native` and require `jovian/fb` (which needs
`ext-fb`) to run the same five engines in C. A missing package is the
container's own not-found — Surface never imports `Jovian\`.

### On screen

A CPU canvas presents into an engine-owned window. `Stage::emulate()`
is a panel at whole-pixel zoom (`INTEGER_SCALE`, nearest). The same
canvas feeds a real panel in the same tick.

```php
$stage = Stage::emulate('oled', new CPUHost(128, 64, $ssd1306->formatSpec()), zoom: 4);
$stage->onDraw(fn (Drawing2D $g, Frame $f) => $g->fillCircle(64, 32, 20 + 8 * sin($f->time), Color::hex('#fff')));
$stage->show();
```
