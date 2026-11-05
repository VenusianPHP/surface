<?php

namespace Surface\Contracts\Windows\Mail\Staged;

use Surface\Contracts\HumanInput\Modifiers;
use Surface\Contracts\HumanInput\MouseButton;
use Surface\Contracts\Windows\Mail\WindowEventOccurred;
use Surface\Contracts\Windows\Mail\WindowMail;

/**
 * A button went down in the window. clicks counts presses of the same button within the double-click time and distance. x and y are in points from the window's top-left. Posted only while watchInput() is on, for user action; the twin of View\CanvasPressed.
 */
readonly class StagedPressed extends WindowEventOccurred implements WindowMail
{
    public function __construct(
        string $window,
        public float $x,
        public float $y,
        public MouseButton $button,
        public int $clicks,
        public Modifiers $modifiers,
    ) {
        parent::__construct($window);
    }

    public function name(): string
    {
        return "staged.pressed.{$this->window}";
    }
}
