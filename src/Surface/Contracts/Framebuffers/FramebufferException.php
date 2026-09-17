<?php

namespace Surface\Contracts\Framebuffers;

use Surface\Contracts\Core\SurfaceLevelException;

class FramebufferException extends SurfaceLevelException
{
    public static function unsupportedFormat(FormatSpec $spec, string $reason = ''): self
    {
        return new self(trim("{$spec->pixel_format->value} at {$spec->bit_depth->value} bpp is not supported here. {$reason}"));
    }

    public static function extensionMissing(string $extension): self
    {
        return new self("The '{$extension}' extension is not loaded; the native framebuffer driver cannot run.");
    }

    public static function outOfRange(int $x, int $y, int $width, int $height): self
    {
        return new self("({$x}, {$y}) is outside a {$width}x{$height} framebuffer.");
    }

    public static function pageRows(int $page_rows, string $reason): self
    {
        return new self("page_rows {$page_rows}: {$reason}");
    }
}
