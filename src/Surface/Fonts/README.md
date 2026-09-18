# surface/fonts

Bitmap faces for Surface. `Fonts::face('helvb-12')` answers a `GFXFont`; the
sketch hands it to `Drawing2D::text()`. Registry only — drawing lives in
`surface/drawing`, face data in `venusian/letterhead`.

```php
$hud = Fonts::face('helvb-12');      // registry, once
$mono = Fonts::face();               // classic 5x7
$g->text('HELLO', 0.0, 0.0, Color::hex('#fff'), $hud);
```

`config/fonts.php`: `default` slug (`FONT_FACE`), `faces` map
`slug => ['class', 'enabled']`. `php computer make:font Name` scaffolds a
face under `app/Fonts`; `--from=FreeSans9pt7b.h` imports an Adafruit header.
