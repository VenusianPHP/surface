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

## Bitmap text

Same hook, any engine. A face is a value from the registry; the Painter
draws it from a glyph atlas, the Rasterizer as spans.

```php
$hud = Fonts::face('helvb-12');                      // venusian/letterhead
$canvas->onDraw(fn (Drawing2D $g, Frame $f) => $g
    ->clear(Color::hex('#000'))
    ->text("T {$c}C", 0.0, 0.0, Color::hex('#fff'), $hud)
    ->text('OK', 100.0, 56.0, Color::hex('#0f0'), Fonts::face()));   // classic 5x7
```

`php computer make:font Name --from=FreeSans9pt7b.h` imports an Adafruit
header. Scale through the stack; `textBounds()` answers the ink box.

## Embedded displays

A display panel chip is a CPU draw target. Attach it and draw with the same
hook; one frame per tick goes out on the IOPool dock. A panel that addresses
a window (OLED, TFT) receives only what changed, any other panel the whole
frame, ePaper a refresh after the write.

```php
$display = EmbeddedDisplay::panel('st7789');                    // wired from the IC catalog, booted, ready
$display->onDraw(fn (Drawing2D $g, Frame $f) => $g
    ->clear(Color::hex('#000'))
    ->text(date('H:i:s'), 0.0, 0.0, Color::hex('#fff'), Fonts::face()));
```

`panel()` conjures the chip from the IC catalog — `config/circuits/st7789.php`
holds the bus, the pins and the geometry, so no SPI and no pin numbers
appear in sketch code. It is idempotent, and it starts no window: a panel
program is the IOPool dock and nothing else.

Hand it a panel yourself when you have one: `EmbeddedDisplay::attach($panel,
'oled')`, or `attach($panel, 'oled', 'paged')` to stream page by page from
one page of RAM. `display('oled')` looks up what is already attached;
`config/embedded-displays.php` says which engine each panel kind gets.

A panel write that throws latches a fault and mails `display.faulted.<name>`
once. Panels come from `dept-of-scrapyard-robotics/*`; the contract they
implement lives in `gpio/contracts`, and the panel must also speak Surface's
`FormatSpecification` so the canvas knows how to pack its bytes.

## Canvas

One lifecycle over whatever a sketch draws into: a GPU view, a GPU or CPU
stage, an embedded display. A `Canvas` does not draw — `draw()` registers
one hook and hands it the output's own `Drawing2D` inside the frame, so a
verb reaches the rasteriser unchanged and damage is exactly what was inked.

```php
$canvas = Canvas::of($display);                                  // or $window->gpu(...), Stage::open(...), Stage::emulate(...)

$canvas->draw(function (Drawing2D $g, Frame $f) use ($white, $font) {
    $g->fillCircle(64.0, 32.0, 20.0, $white)->text('READY', 0.0, 0.0, $white, $font);
})->present();                                                   // one frame; ->animate() for a stream

$canvas->hide();  $canvas->show();  $canvas->close();
```

Whether last frame's pixels survive is the output's business: a GPU target
clears to `background()` and wants the whole scene again, a `dirty` or
`epaper` canvas keeps what it has, so a panel sketch inks only what changed
and the damage band stays small.

### Pick the engine, not just the output

Where the pixels go and what makes them are two choices. `engine()` is the
second one, and it works on all four outputs:

```php
Canvas::of(EmbeddedDisplay::panel('st7789'))->engine('metal');   // Metal rasterises an SPI panel
Canvas::of(Stage::open('scene', null, 320, 240))->engine('dirty'); // a CPU engine rasterises a GPU window
```

A GPU engine needs no window — it attaches to nothing, draws into a surface
it made itself, and the frame is read back: into the panel's own bytes for a
display, into one texture for a window. A panel or a CPU stage takes the
renderer outright and keeps its size, format and lifecycle; a GPU stage or
view owns its surface, so the renderer draws offscreen and arrives as a
texture. Asking a GPU target for the engine it already runs draws straight
through, with no offscreen at all.

`rasteriser()` answers which engine is drawing. `output()->engine()` does
not: a panel drawn by Metal still holds finished pixels, so it still says
`CPUEngine`.

Type-hint `Surface\Contracts\Canvas\Canvasable`. `output()` answers the
wrapped object for what only its kind has; `drawing()` reaches the
rasteriser's drawer outside a frame for a texture or a measurement.
