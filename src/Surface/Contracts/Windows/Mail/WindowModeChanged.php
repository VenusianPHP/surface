<?php

namespace Surface\Contracts\Windows\Mail;

use Surface\Contracts\Windows\WindowMode;

/** The window entered a mode: maximized, minimized, restored to windowed, fullscreen or exclusive. */
readonly class WindowModeChanged extends WindowEventOccurred implements WindowMail
{
    public function __construct(
        string $window,
        public WindowMode $mode,
    ) {
        parent::__construct($window);
    }

    public function name(): string
    {
        return "window.mode-changed.{$this->window}";
    }
}
