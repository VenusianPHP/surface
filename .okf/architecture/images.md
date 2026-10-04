---
type: Module
title: Images
description: PNG, JPEG and TIFF bytes into full RGBA8 framebuffers; ext-gd + PHP (native) or C (extended, ext-imgdec).
resource: src/Surface/Images/
tags: [surface, images, png, jpeg, tiff]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-04T16:11:24Z }
sources:
  - id: contract
    resource: src/Surface/Contracts/Images/ImageDecoder.php
    title: ImageDecoder, the decode rules both drivers keep
  - id: base
    resource: src/Surface/Images/ImageDecoder.php
    title: Shared sniff, header size, limits, JPEG end check, framebuffer
  - id: gd
    resource: src/Surface/Images/Native/GdReader.php
    title: GdReader, the three ways out of gd
  - id: tiff
    resource: src/Surface/Images/Native/TiffReader.php
    title: TiffReader, TIFF in PHP
---

# Overview

Images = encoded bytes → pixels. Answers a full RGBA8 framebuffer; draw it with `paintImage()` or a RenderingEngine's `image()`. For outputs with no toolkit decoder: `TKCanvas`, GPIO panels, staged windows, GPU textures. Toolkit image primitives decode for themselves.[^contract]

```php
$tile = app('images')->decode($response->body());
$canvas->paintImage($tile, Affine::translation(256.0, 0.0));
```

Requires Framebuffers (output is a framebuffer); nothing requires Images.

# Structure

| Layer | Class | Does |
|---|---|---|
| Shared | `ImageDecoder` (abstract) | `ImageFormat::sniff()`, size from header, limits, JPEG end marker, `full(rgba8)->writeRgba8()` on its framebuffer driver |
| Native | `NativeImageDecoder` | PNG/JPEG → `GdReader` (ext-gd), TIFF → `TiffReader` (PHP) |
| Extended | `ExtendedImageDecoder` | `imgdec_png/jpeg/tiff()` from ext-imgdec |
| Manager | `ImagesManager` | `app('images')`; `driver()`, `decode()` forwarded to default driver |

Framebuffer driver: `images.framebuffers`, else `framebuffers.default`.[^base]

# Rules

Straight RGBA8, both drivers:

* PNG: palette + grey expanded, tRNS → alpha, 16-bit → high byte, Adam7 undone, no gamma.
* JPEG: libjpeg default decode; grey → RGB; CMYK as gd (inverted under Adobe marker); alpha 255. Refused when no end marker follows the first scan (cut-off download), both drivers.
* TIFF: classic only (BigTIFF fails sniff); first image; chunky or separate planes (GIBS WMS writes planes); orientation 1; grey either polarity 1/2/4/8/16, RGB 8/16, palette 1/2/4/8 (map high byte); unsigned; first extra sample alpha kept, associated divided out `min(255, round(c × 255 ÷ a))`.
* Limits before any pixel: sides ≤ 65535, ≤ 2²⁶ pixels. `ImageException` names format + reason.

# Native

`GdReader` decodes with gd, then gets bytes out by the fastest working route:[^gd]

| Image | Route |
|---|---|
| palette (PNG palette, grey < 16 bit) | 8-bit BMP of indices, `strtr()` through `imagecolorsforindex()`; gd's own truecolor conversion zeroes transparent entries on the bundled build |
| truecolor, PHP's bundled gd | stored PNG, filter none: inflate, drop filter bytes |
| truecolor opaque, system gd | 24-bit BMP, one `preg_replace()` BGR → RGBA |
| truecolor with alpha, system gd | `imagecolorat()` per pixel |

gd holds alpha in 7 bits: alpha `a` comes back `255 − (2q + q ≫ 6)`, `q = 127 − (a ≫ 1)`; 0 and 255 exact, rest within one. gd keeps an RGB/grey tRNS colour opaque, so `GdReader` clears it after, on 8-bit samples (16-bit: high bytes).

Measured, 512² tile: Pi (bundled gd) ~4 ms decode + 2–7 ms out; Mac (system gd) opaque ~24 ms out, alpha ~60 ms out.

`TiffReader`: none, PackBits, LZW (libtiff's: MSB-first, early change), Deflate; predictor 2 at 8/16 bits, under LZW/Deflate only (as libtiff); strips or tiles; planes read as one-sample images, spread with zeros and ORed into chunky rows. Byte-wide work through `strtr()`, `preg_replace()`, string XOR and OR.[^tiff]

# Extended

ext-imgdec 0.10: libpng, libjpeg, libtiff. Every alpha bit; tRNS on all 16 bits; any compression libtiff reads. `ImgdecException` code `IMGDEC_UNSUPPORTED` → `ImageException::unsupported()`, else `corrupt()`.

Measured on the Mac, extended vs native: 512² JPEG tile 1.1 vs 24 ms; 512² RGBA PNG 1.0 vs 56 ms; 700x350 planar TIFF 0.7 vs 32 ms. `ParityTest`: JPEG identical across drivers; PNG identical but for gd's alpha; 16-bit tRNS the one documented split.

[^contract]: ImageDecoder, the decode rules both drivers keep
[^base]: Shared sniff, header size, limits, JPEG end check, framebuffer
[^gd]: GdReader, the three ways out of gd
[^tiff]: TiffReader, TIFF in PHP
