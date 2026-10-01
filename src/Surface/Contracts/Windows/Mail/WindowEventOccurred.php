<?php

namespace Surface\Contracts\Windows\Mail;

abstract readonly class WindowEventOccurred implements WindowMail
{
    public function __construct(
        public string $window
    ) {}

    /**
     * @return string
     */
    abstract public function name(): string;

    /**
     * @return string
     */
    public function window(): string
    {
        return $this->window;
    }
}