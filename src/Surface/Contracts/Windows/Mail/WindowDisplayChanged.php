<?php

namespace Surface\Contracts\Windows\Mail;

/** The window moved to another display. */
readonly class WindowDisplayChanged extends WindowEventOccurred implements WindowMail
{
    public function __construct(
        string $window,
        public int $displayId,
    ) {
        parent::__construct($window);
    }

    public function name(): string
    {
        return "window.display-changed.{$this->window}";
    }
}
