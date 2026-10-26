<?php

namespace Surface\Contracts\Windows\Mail\View;

/**
 * The pointer crossed out of a watching, enabled canvas, or left the window. Nothing posts during
 * an owned press; a press released outside the canvas posts this after CanvasReleased.
 */
readonly class CanvasLeft extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function name(): string
    {
        return "view.left.{$this->window}.{$this->path}";
    }
}
