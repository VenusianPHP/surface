<?php

namespace Surface\Contracts\Drawing;

use Surface\Contracts\Core\SurfaceLevelException;

class DrawingException extends SurfaceLevelException
{
    public static function outsideFrame(string $operation): self
    {
        return new self("{$operation} is only legal between beginFrame() and endFrame().");
    }

    public static function emptyStack(): self
    {
        return new self('pop() with nothing pushed.');
    }

    public static function pagedHostOnly(): self
    {
        return new self('A paged canvas flushes its host format only; transcode from a full canvas.');
    }
}
