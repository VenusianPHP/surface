# venusian-surface/fonts

Surface fonts: bitmap faces (`GFXFont`, drawn on the CPU) and TrueType faces
(`TrueTypeFont`, drawn by every engine) through ext-only drivers.
`app('fonts')->face('helvb-12')` answers a `Font`; hand it to a rendering
engine's `text()`. Registry only: drawing lives in
`venusian-surface/drawing`.

```php
$face = app('fonts')->face();        // the default: classic 5x7
$engine = app('drawing')->renderer('velvet', ['width' => 128, 'height' => 64]);

$engine->frame(function ($g) use ($face) {
    $g->clear(Color::rgb(0, 0, 0));
    $g->text('HELLO', 2, 2, Color::rgb(255, 255, 255), $face);
    $g->translate(2, 14)->scale(2)->text('BIG', 0, 0, Color::rgb(255, 200, 0), $face);
});
```

`config/fonts.php`: `default` slug (`FONT_FACE`), `faces` map
`slug => ['class', 'enabled']`. `app('fonts')->extend('slug', Face::class)`
adds one at run time. `php computer make:font Name` scaffolds a face under
`app/Fonts`; `--from=FreeSans9pt7b.h` imports an Adafruit GFX header.

TrueType needs ext-freetype, ext-harfbuzz and ext-fontconfig; without them a
TrueType `face()` throws `FontException::extensionsMissing()` and bitmap faces
work unchanged.

```php
// config/fonts.php: 'faces' => ['ui' => ['pattern' => 'sans-serif', 'size' => 13, 'enabled' => true]]
$ui = app('fonts')->face('ui');                       // TrueTypeFont
app('fonts')->truetype('mono', 'monospace', 12);      // or at run time
$engine->frame(fn ($g) => $g->text('Hello', 4, 4, Color::rgb(230, 230, 230), $ui));
```
