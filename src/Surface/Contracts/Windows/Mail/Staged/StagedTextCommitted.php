<?php

namespace Surface\Contracts\Windows\Mail\Staged;
use Surface\Contracts\Windows\Mail\WindowEventOccurred;
use Surface\Contracts\Windows\Mail\WindowMail;

/**
 * Text the input method committed while the window has key focus. Posted only while watchInput() is on, for user action; the twin of View\TextCommitted.
 */
readonly class StagedTextCommitted extends WindowEventOccurred implements WindowMail
{
    public function __construct(
        string $window,
        public string $text,
    ) {
        parent::__construct($window);
    }

    public function name(): string
    {
        return "staged.text-committed.{$this->window}";
    }
}
