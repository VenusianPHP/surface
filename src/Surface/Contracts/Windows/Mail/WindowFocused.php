<?php

namespace Surface\Contracts\Windows\Mail;

readonly class WindowFocused extends WindowEventOccurred implements WindowMail
{
    /**
     * @return string
     */
    public function name(): string
    {
        return "window.focused.{$this->window}";
    }
}