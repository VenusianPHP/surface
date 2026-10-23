<?php

namespace Surface\Contracts\Windows\Mail\View;

use Surface\Contracts\HumanInput\Modifiers;
use Surface\Contracts\HumanInput\MouseButton;

/**
 * A button went down on a watching, enabled canvas, and that canvas now owns the press: until it
 * ends, no other button's press or release posts anything. x and y are in points from the canvas's
 * top-left, as CanvasClicked's. A touch the toolkit emulates a pointer with is the left button.
 * clicks counts presses of the same button on the same canvas within the toolkit's double-click
 * time and distance, so a triple click is 3 on every toolkit.
 */
readonly class CanvasPressed extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public float $x,
        public float $y,
        public MouseButton $button,
        public int $clicks,
        public Modifiers $modifiers,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    public function name(): string
    {
        return "view.pressed.{$this->window}.{$this->path}";
    }
}
