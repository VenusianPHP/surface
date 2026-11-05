<?php

namespace Surface\Contracts\Windows\Mail\Staged;

use Surface\Contracts\HumanInput\Modifiers;
use Surface\Contracts\HumanInput\MouseButton;
use Surface\Contracts\Windows\Mail\WindowEventOccurred;
use Surface\Contracts\Windows\Mail\WindowMail;

/**
 * The pointer moved while the window owns a press. x and y are in points from the window's top-left. Posted only while watchInput() is on, for user action; the twin of View\CanvasDragged.
 */
readonly class StagedDragged extends WindowEventOccurred implements WindowMail
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
        return "staged.dragged.{$this->window}";
    }
}
