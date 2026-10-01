<?php

namespace Surface\Contracts\Windows\Mail;

readonly class WindowClosed extends WindowEventOccurred implements WindowMail
{

    /**
     * @return string
     */
    public function name(): string
    {
        return "window.closed.{$this->window}";
    }
}