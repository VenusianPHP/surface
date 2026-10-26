<?php

namespace Surface\Contracts\Windows\Mail\View;

use Surface\Contracts\HumanInput\Modifiers;

/**
 * The pointer moved over a watching, enabled canvas with no button held; with a button held
 * CanvasDragged posts instead. x and y in the canvas's points. Latest wins.
 */
readonly class CanvasMoved extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public float $x,
        public float $y,
        public Modifiers $modifiers,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    public function name(): string
    {
        return "view.moved.{$this->window}.{$this->path}";
    }
}
