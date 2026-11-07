<?php

namespace Surface\Contracts\Fonts;

use Surface\Contracts\Core\SurfaceException;

class FontException extends SurfaceException
{
    public static function unknown(string $slug): self
    {
        return new self("Font [{$slug}] is not registered.");
    }

    public static function notAFont(string $class): self
    {
        return new self("[{$class}] must be a concrete subclass of ".GFXFont::class.'.');
    }

    public static function invalidHeader(string $reason): self
    {
        return new self($reason);
    }

    public static function extensionsMissing(): self
    {
        return new self('TrueType fonts need ext-freetype, ext-harfbuzz and ext-fontconfig.');
    }

    public static function badSize(float $size): self
    {
        return new self("A TrueType size must be finite and above zero, not [{$size}].");
    }

    public static function notFound(string $pattern): self
    {
        return new self("No font matches [{$pattern}].");
    }

    public static function library(string $what): self
    {
        return new self("The font library failed: {$what}.");
    }
}
