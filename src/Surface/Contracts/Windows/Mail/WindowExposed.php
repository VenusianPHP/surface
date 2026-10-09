<?php

namespace Surface\Contracts\Windows\Mail;

/** The window shows again after WindowOccluded. */
readonly class WindowExposed extends WindowEventOccurred implements WindowMail
{

    public function name(): string
    {
        return "window.exposed.{$this->window}";
    }
}
