<?php

namespace Surface\Contracts\Windows\Mail\View;

/**
 * The user right-clicked this primitive, the innermost one under the pointer: the OS's secondary
 * click (right button, two-finger or Magic Mouse right click, Ctrl-click on macOS) or, on Linux, a
 * primary press held still (how a touchscreen registered as a pointer right-clicks). x and y from the
 * primitive's top-left.
 */
readonly class RightClicked extends PrimitiveEventOccurred implements PrimitiveMail
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
        return "view.right-clicked.{$this->window}.{$this->path}";
    }
}
