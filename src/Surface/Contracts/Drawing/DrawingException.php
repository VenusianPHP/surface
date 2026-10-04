<?php

namespace Surface\Contracts\Drawing;

use Surface\Contracts\Core\SurfaceException;

class DrawingException extends SurfaceException
{
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
