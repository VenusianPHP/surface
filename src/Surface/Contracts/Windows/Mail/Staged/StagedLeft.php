<?php

namespace Surface\Contracts\Windows\Mail\Staged;
use Surface\Contracts\Windows\Mail\WindowEventOccurred;
use Surface\Contracts\Windows\Mail\WindowMail;

/**
 * The pointer went out of the window. Posted only while watchInput() is on, for user action; the twin of View\CanvasLeft.
 */
readonly class StagedLeft extends WindowEventOccurred implements WindowMail
{
    public function name(): string
    {
        return "staged.left.{$this->window}";
    }
}
