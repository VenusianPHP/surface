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
}
