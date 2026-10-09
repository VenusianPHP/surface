<?php

namespace Surface\Contracts\Windows\Mail;

/** The window stopped being key. */
readonly class WindowFocusLost extends WindowEventOccurred implements WindowMail
{

    public function name(): string
    {
        return "window.focus-lost.{$this->window}";
    }
}
