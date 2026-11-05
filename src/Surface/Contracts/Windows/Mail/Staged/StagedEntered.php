<?php

namespace Surface\Contracts\Windows\Mail\Staged;
use Surface\Contracts\Windows\Mail\WindowEventOccurred;
use Surface\Contracts\Windows\Mail\WindowMail;

/**
 * The pointer came into the window. Posted only while watchInput() is on, for user action; the twin of View\CanvasEntered.
 */
readonly class StagedEntered extends WindowEventOccurred implements WindowMail
{
    public function name(): string
    {
        return "staged.entered.{$this->window}";
    }
}
