<?php

namespace Surface\Contracts\Windows\Mail;

/** The user asked to close a window opened with confirm_close; it stays open until close(). */
readonly class WindowCloseRequested extends WindowEventOccurred implements WindowMail
{

    public function name(): string
    {
        return "window.close-requested.{$this->window}";
    }
}
