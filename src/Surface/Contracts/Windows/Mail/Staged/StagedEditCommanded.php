<?php

namespace Surface\Contracts\Windows\Mail\Staged;
use Surface\Contracts\Windows\Input\EditCommand;
use Surface\Contracts\Windows\Mail\WindowEventOccurred;
use Surface\Contracts\Windows\Mail\WindowMail;

/**
 * An edit command the platform's key bindings or Edit menu gave while the window has key focus. Posted only while watchInput() is on, for user action; the twin of View\EditCommanded.
 */
readonly class StagedEditCommanded extends WindowEventOccurred implements WindowMail
{
    public function __construct(
        string $window,
        public EditCommand $command,
    ) {
        parent::__construct($window);
    }

    public function name(): string
    {
        return "staged.edit-commanded.{$this->window}";
    }
}
