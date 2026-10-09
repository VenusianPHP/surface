<?php

namespace Surface\Contracts\Windows\Mail;

/** The window moved; x and y in points on the desktop. Latest wins. */
readonly class WindowMoved extends WindowEventOccurred implements WindowMail
{
    public function __construct(
        string $window,
        public int $x,
        public int $y,
    ) {
        parent::__construct($window);
    }

    public function name(): string
    {
        return "window.moved.{$this->window}";
    }
}
