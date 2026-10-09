<?php

namespace Surface\Contracts\Windows\Mail;

/** The display link fired: timestamp is this refresh, targetTimestamp the one the next frame shows at, in seconds. Latest wins. */
readonly class WindowFrameDue extends WindowEventOccurred implements WindowMail
{
    public function __construct(
        string $window,
        public float $timestamp,
        public float $targetTimestamp,
    ) {
        parent::__construct($window);
    }

    public function name(): string
    {
        return "window.frame-due.{$this->window}";
    }
}
