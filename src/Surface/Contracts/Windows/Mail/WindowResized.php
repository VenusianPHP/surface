<?php

namespace Surface\Contracts\Windows\Mail;

readonly class WindowResized extends WindowEventOccurred implements WindowMail
{
    public function __construct(
        string $window,
        public readonly int $width,
        public readonly int $height,
    ) {
        parent::__construct($window);
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return "window.resized.{$this->window}";
    }
}
