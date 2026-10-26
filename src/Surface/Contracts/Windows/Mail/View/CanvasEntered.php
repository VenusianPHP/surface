<?php

namespace Surface\Contracts\Windows\Mail\View;

/**
 * The pointer crossed into a watching, enabled canvas. Nothing posts during an owned press.
 */
readonly class CanvasEntered extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function name(): string
    {
        return "view.entered.{$this->window}.{$this->path}";
    }
}
