<?php

namespace Surface\Contracts\Windows\Mail;

/** Device pixels per point changed: a framebuffer sized to the window wants remaking. */
readonly class WindowScaleChanged extends WindowEventOccurred implements WindowMail
{
    public function __construct(
        string $window,
        public float $scale,
    ) {
        parent::__construct($window);
    }

    public function name(): string
    {
        return "window.scale-changed.{$this->window}";
    }
}
