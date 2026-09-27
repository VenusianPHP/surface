<?php

namespace Surface\Contracts\Canvas;

use Surface\Contracts\Core\SurfaceLevelException;

/** Canvas failures Surface can name. */
class CanvasException extends SurfaceLevelException
{
    public static function unsupportedOutput(string $type): static
    {
        return new static("A Canvas cannot draw into {$type}. Give it a GPU view, a stage or an embedded display.");
    }

    public static function unsupported(string $verb, CanvasKind $kind): static
    {
        return new static("A '{$kind->value}' canvas cannot {$verb}().");
    }

    public static function closed(): static
    {
        return new static('This canvas is closed.');
    }
}
