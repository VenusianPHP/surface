# venusian-surface/fonts

Bitmap faces for Surface. `app('fonts')->face('helvb-12')` answers a `GFXFont`;
hand it to a rendering engine's `text()`. Registry only: drawing lives in
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
