<?php

namespace Surface\Contracts\Windows\Mail\Staged;

use Surface\Contracts\HumanInput\Modifiers;
use Surface\Contracts\Windows\Mail\WindowEventOccurred;
use Surface\Contracts\Windows\Mail\WindowMail;

/**
 * The pointer moved over the window with no button down. x and y are in points from the window's top-left. Posted only while watchInput() is on, for user action; the twin of View\CanvasMoved.
 */
readonly class StagedMoved extends WindowEventOccurred implements WindowMail
{
    public function __construct(
        string $window,
        public float $x,
        public float $y,
        public Modifiers $modifiers,
    ) {
        parent::__construct($window);
    }

    public function name(): string
    {
        return "staged.moved.{$this->window}";
    }
}
