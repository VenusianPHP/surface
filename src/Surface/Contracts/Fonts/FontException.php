<?php

namespace Surface\Contracts\Fonts;

use Surface\Contracts\Core\SurfaceLevelException;

class FontException extends SurfaceLevelException
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

    public static function atlasTooLarge(string $class, int $needed, int $max): self
    {
        return new self("[{$class}] needs a {$needed}px glyph atlas; the executor allows {$max}px.");
    }
}
