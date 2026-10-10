<?php

namespace Surface\Contracts\Windows\Mail;

/**
 * The user right-clicked the window's bare content, where no primitive is: see View\RightClicked
 * for what counts as a right click. x and y in the content area, as HumanInput's mouse reads them.
 */
readonly class WindowRightClicked extends WindowEventOccurred implements WindowMail
{
    public function __construct(
        string $window,
        public float $x,
        public float $y,
    ) {
        parent::__construct($window);
    }

    public function name(): string
    {
        return "window.right-clicked.{$this->window}";
    }
}
