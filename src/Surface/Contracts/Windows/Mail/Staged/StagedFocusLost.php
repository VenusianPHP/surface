<?php

namespace Surface\Contracts\Windows\Mail\Staged;
use Surface\Contracts\Windows\Mail\WindowEventOccurred;
use Surface\Contracts\Windows\Mail\WindowMail;

/**
 * The window lost key focus for its input. Posted only while watchInput() is on, for user action; the twin of View\FocusLost.
 */
readonly class StagedFocusLost extends WindowEventOccurred implements WindowMail
{
    public function name(): string
    {
        return "staged.focus-lost.{$this->window}";
    }
}
