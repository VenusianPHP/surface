<?php

namespace Surface\Contracts\Rasterize;

use Surface\Contracts\Core\SurfaceException;
use Surface\Contracts\Framebuffers\Region;

class RasterizeException extends SurfaceException
{
    public static function notFinite(string $what): self
    {
        return new self("{$what} is not a finite number.");
    }

    public static function pastLimit(string $what, float $value, float $limit): self
    {
        return new self("{$what} {$value} is past ±{$limit}.");
    }

    public static function notAPoint(int $position): self
    {
        return new self("Point {$position} is not [x, y].");
    }

    public static function clip(Region $clip): self
    {
        return new self("The clip {$clip->width}x{$clip->height} at ({$clip->x}, {$clip->y}) is empty or not inside 0..65535.");
    }

    public static function extensionMissing(): self
    {
        return new self("The 'extended' rasterize driver needs ext-rasterize 0.10 or newer loaded in this PHP (".PHP_BINARY.').');
    }
}
