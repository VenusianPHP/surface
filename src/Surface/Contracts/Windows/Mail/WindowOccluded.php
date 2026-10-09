<?php

namespace Surface\Contracts\Windows\Mail;

/** The window is fully covered, minimized or on another Space: nothing drawn shows. */
readonly class WindowOccluded extends WindowEventOccurred implements WindowMail
{

    public function name(): string
    {
        return "window.occluded.{$this->window}";
    }
}
