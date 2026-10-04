# venusian-surface/embedded-displays

An IC display panel as a drawing output, beside a toolkit canvas. A display
hands out a framebuffer in the panel's own format; draw into it with a
rendering engine, then `present()` sends the panel what changed.

```php
$display = app('displays')->panel('st7789');    // conjured from config/circuits/st7789.php
$engine = app('drawing')->renderer('velvet', ['framebuffer' => $display->framebuffer()]);
$face = app('fonts')->face();

while (true) {
    $engine->frame(function ($g) use ($face) {
        $g->clear(Color::rgb(0, 0, 32));
        $g->strokeRect(1, 1, 238, 238, Color::rgb(255, 255, 255));
        $g->scale(4)->text(date('H:i:s'), 6, 26, Color::rgb(255, 255, 0), $face);
    });
    $display->present();                        // the first frame whole, then only the time
    sleep(1);
}
```

A chip the sketch built itself: `app('displays')->attach($st7789, 'tft')`. It
must have booted and answer `formatSpec()`.

## What present() sends

| Panel | Framebuffer `framebuffer()` picks | Sent |
|---|---|---|
| ePaper (refreshes on command) | `epaper` | the whole frame, then one `refresh()` |
| Region writes (OLED, TFT) | `dirty` | the whole frame first, then the damage, snapped to what the panel addresses (8-row pages, byte columns) |
| Whole frames only (LED matrix) | `full` | the whole frame |

`config/embedded-displays.php` `defaults` changes the kind per panel
behaviour. `framebuffer('ring', frames: 3)` and
`framebuffer('paged', page_rows: 8)` name one; a paged framebuffer is sent page
by page, the refresh after the last.

A rendering engine draws only what changed since its last frame, so a dirty
framebuffer under it records only that, and the panel gets only that.

`bind($framebuffer)` shows a framebuffer made elsewhere, in any format: its
bytes are converted to the panel's when sent.

`show()` / `hide()` switch a panel that can. A panel call that throws stops the
display and posts one `DisplayFaulted` mail (`display.faulted.<name>`) to the
event loop; `detach()` and attach again to recover. `close()` switches the panel
off and leaves the chip and its bus to the sketch.

## Panels

`dept-of-scrapyard-robotics/ssd1306`, `/st77xx` (ST7735, ST7789, ST7796),
`/ssd1680` (black/white, black/white/red), `/jd79661`, `/spectra6`, over
`scrapyard-io/framework` buses.
