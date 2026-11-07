<?php

namespace Surface\Contracts\Drawing;

use Surface\Contracts\Core\SurfaceException;

class DrawingException extends SurfaceException
{
    public static function unevenShadow(): self
    {
        return new self('shadow() needs an upright transform that scales x and y alike.');
    }

    public static function badRadii(): self
    {
        return new self('Radii are a list of four numbers: [tl, tr, br, bl].');
    }

    public static function unknownFont(string $class): self
    {
        return new self("Text cannot be drawn with [{$class}]: it is neither a GFXFont nor a TrueTypeFont.");
    }

    public static function atlasFull(int $max): self
    {
        return new self("One frame's glyph masks do not fit in a {$max}x{$max} atlas.");
    }

    public static function noAtlas(): self
    {
        return new self('TrueType glyphs need a glyph atlas to be lowered.');
    }

    public static function outsideFrame(string $call): self
    {
        return new self("{$call}() belongs inside a frame: between begin() and end(), or in the callable given to frame().");
    }

    public static function notFinite(string $what): self
    {
        return new self("{$what} is not a finite number.");
    }

    public static function pastLimit(string $what, float $value): self
    {
        return new self("{$what} {$value} is past ±".RenderingEngine::LIMIT.' once transformed.');
    }

    public static function notAPoint(int $position): self
    {
        return new self("Point {$position} is not [x, y].");
    }
}
