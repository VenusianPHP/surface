<?php

namespace Surface\Contracts\Windows\Mail\Staged;

use Surface\Contracts\HumanInput\Modifiers;
use Surface\Contracts\Windows\Mail\View\ScrollPhase;
use Surface\Contracts\Windows\Mail\WindowEventOccurred;
use Surface\Contracts\Windows\Mail\WindowMail;

/**
 * The wheel or a trackpad scrolled over the window. Posted only while watchInput() is on, for user action; the twin of View\CanvasWheeled.
 */
readonly class StagedWheeled extends WindowEventOccurred implements WindowMail
{
    public function __construct(
        string $window,
        public float $dx,
        public float $dy,
        public bool $precise,
        public Modifiers $modifiers,
        public ScrollPhase $phase = ScrollPhase::NONE,
    ) {
        parent::__construct($window);
    }

    public function name(): string
    {
        return "staged.wheeled.{$this->window}";
    }
}
