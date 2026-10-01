<?php

namespace Surface\Contracts\Windows\Mail;

readonly class MenuActivated extends WindowEventOccurred implements WindowMail
{
    public function __construct(
        string $window,
        public readonly string $item,
    ) {
        parent::__construct($window);
    }

    public function name(): string
    {
        return "menu.activated.{$this->window}.{$this->item}";
    }
}