<?php

namespace Surface\Contracts\Windows\Mail\View;

use Surface\Contracts\HumanInput\Modifiers;
use Surface\Contracts\HumanInput\MouseButton;

/**
 * The press ended: exactly one of these follows every CanvasPressed. It is posted at the owning
 * button's release, wherever the pointer is by then, or, when the toolkit ends the press without
 * one (a context menu opens, GTK's grab breaks, a touch is cancelled), at once, at the last point
 * posted. A press ended from code posts nothing at all, as no mail comes from code.
 */
readonly class CanvasReleased extends PrimitiveEventOccurred implements PrimitiveMail
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
        return "view.released.{$this->window}.{$this->path}";
    }
}
