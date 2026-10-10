<?php

namespace Surface\Contracts\Windows\Mail\View;

/**
 * The user clicked this canvas: a primary press and its release on it, the pointer not moved past
 * the toolkit's drag distance (a touchscreen tap included). Named as a button's click. x and y are
 * where the press landed, in points from the canvas's top-left; size() against pixelSize() turns
 * them into framebuffer pixels.
 */
readonly class CanvasClicked extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public float $x,
        public float $y,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    public function name(): string
    {
        return "view.clicked.{$this->window}.{$this->path}";
    }
}
