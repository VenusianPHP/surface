<?php

namespace Surface\Contracts\Drawing;

use Surface\Contracts\Fonts\GFXFont;
use Surface\Contracts\NativeWindows\Views\Color;

/**
 * The common 2D drawing API. Floats, Color, top-left pixels in the target's
 * own point space, fluent. Bitmap text comes from a GFXFont the sketch holds.
 *
 * push/pop/translate/rotate/scale drive a 2D affine stack applied to every
 * vertex as it is emitted. clip() is an axis-aligned scissor in UNTRANSFORMED
 * target space. When the executor reports blending false, alpha is ignored
 * and everything draws opaque.
 */
interface Drawing2D
{
    public function clear(Color $color): static;

    public function fillRect(float $x, float $y, float $width, float $height, Color $color): static;

    public function strokeRect(float $x, float $y, float $width, float $height, Color $color, float $stroke = 1.0): static;

    public function line(float $x0, float $y0, float $x1, float $y1, Color $color, float $stroke = 1.0): static;

    /** @param list<array{float, float}> $points */
    public function polyline(array $points, Color $color, float $stroke = 1.0, bool $closed = false): static;

    public function fillTriangle(float $x0, float $y0, float $x1, float $y1, float $x2, float $y2, Color $color): static;

    /** Convex only; fan-as-list from vertex 0. @param list<array{float, float}> $points */
    public function fillPolygon(array $points, Color $color): static;

    public function fillCircle(float $cx, float $cy, float $radius, Color $color): static;

    public function strokeCircle(float $cx, float $cy, float $radius, Color $color, float $stroke = 1.0): static;

    public function fillEllipse(float $cx, float $cy, float $rx, float $ry, Color $color): static;

    /** @param array{float, float, float, float}|null $source [x, y, w, h] in texels; null is the whole texture */
    public function image(TextureHandle $texture, float $x, float $y, float $width, float $height, ?array $source = null, float $alpha = 1.0): static;

    public function texture(string $rgba8, int $width, int $height): TextureHandle;

    /**
     * The handle dies now. A sketch that mints a texture per frame must call
     * this or it leaks: on a GPU engine the executor's texture stays alive
     * until the frame it was released in submits.
     */
    public function releaseTexture(TextureHandle $texture): static;

    public function push(): static;

    public function pop(): static;

    public function translate(float $dx, float $dy): static;

    public function rotate(float $radians): static;

    public function scale(float $sx, float $sy): static;

    public function clip(float $x, float $y, float $width, float $height): static;

    public function unclip(): static;

    /** @return array{int, int} target size in points */
    public function size(): array;

    /**
     * Bitmap text from the face's own glyphs. ($x, $y) is the top-left of the
     * first line box; "\n" starts a new line, "\r" is ignored, codes outside
     * the face are skipped. Scale and rotate through the stack.
     */
    public function text(string $text, float $x, float $y, Color $color, GFXFont $font): static;

    /** @return array{float, float, float, float} ink box [x, y, w, h] relative to the text origin, untransformed; all zero when nothing inks */
    public function textBounds(string $text, GFXFont $font): array;
}
