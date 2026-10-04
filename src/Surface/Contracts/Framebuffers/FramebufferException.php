<?php

namespace Surface\Contracts\Framebuffers;

use Surface\Contracts\Core\SurfaceException;

class FramebufferException extends SurfaceException
{
    public static function unsupportedFormat(FormatSpec $spec, string $reason = ''): self
    {
        return new self(trim("{$spec->pixel_format->value} at {$spec->bit_depth->value} bpp is not supported here. {$reason}"));
    }

    public static function extensionMissing(string $extension): self
    {
        return new self("The 'extended' framebuffer driver needs ext-{$extension} 0.10 or newer loaded in this PHP (".PHP_BINARY.').');
    }

    public static function outOfRange(int $x, int $y, int $width, int $height): self
    {
        return new self("({$x}, {$y}) is outside a {$width}x{$height} framebuffer.");
    }

    public static function outsideSurface(Region $region, int $width, int $height): self
    {
        return new self("The region {$region->width}x{$region->height} at ({$region->x}, {$region->y}) is empty or not inside a {$width}x{$height} framebuffer.");
    }

    public static function pageRows(int $page_rows, string $reason): self
    {
        return new self("page_rows {$page_rows}: {$reason}");
    }

    public static function notReady(): self
    {
        return new self('Every frame but the front is held; release one before drawing or presenting.');
    }
}
