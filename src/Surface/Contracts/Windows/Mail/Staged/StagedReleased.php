<?php

namespace Surface\Contracts\Windows\Mail\Staged;

use Surface\Contracts\HumanInput\Modifiers;
use Surface\Contracts\HumanInput\MouseButton;
use Surface\Contracts\Windows\Mail\WindowEventOccurred;
use Surface\Contracts\Windows\Mail\WindowMail;

/**
 * The button of the press the window owns went up. x and y are in points from the window's top-left. Posted only while watchInput() is on, for user action; the twin of View\CanvasReleased.
 */
readonly class StagedReleased extends WindowEventOccurred implements WindowMail
{
    public function __construct(
        string $window,
        public float $x,
        public float $y,
        public MouseButton $button,
        public Modifiers $modifiers,
    ) {
        parent::__construct($window);
    }

    public function name(): string
    {
        return "staged.released.{$this->window}";
    }
}
