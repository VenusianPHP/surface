<?php

namespace Surface\Contracts\Windows\Mail\View;

use Surface\Contracts\HumanInput\Modifiers;
use Surface\Contracts\HumanInput\MouseButton;

/**
 * The pointer moved while the owning button is held down, and the point changed: a move to the same
 * point posts nothing. The canvas keeps the press through the toolkit's implicit capture, so this
 * goes on posting outside the canvas and outside the window, x and y then negative or past its size.
 */
readonly class CanvasDragged extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public float $x,
        public float $y,
        public MouseButton $button,
        public Modifiers $modifiers,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    public function name(): string
    {
        return "view.dragged.{$this->window}.{$this->path}";
    }
}
