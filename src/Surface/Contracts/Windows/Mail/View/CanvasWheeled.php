<?php

namespace Surface\Contracts\Windows\Mail\View;

use Surface\Contracts\HumanInput\Modifiers;

/**
 * The wheel turned over a watching, enabled canvas. The canvas keeps the event: an enclosing scroll
 * view does not scroll with it. precise is true for trackpad points, false for wheel notches, one
 * notch being 1.0; positive dx and dy scroll toward the content's right and bottom, with the
 * system's scroll-direction setting applied as the toolkit delivers it. phase places it in a
 * trackpad gesture; a wheel notch is NONE.
 */
readonly class CanvasWheeled extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public float $dx,
        public float $dy,
        public bool $precise,
        public Modifiers $modifiers,
        public ScrollPhase $phase = ScrollPhase::NONE,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    public function name(): string
    {
        return "view.wheeled.{$this->window}.{$this->path}";
    }
}
