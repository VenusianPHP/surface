<?php

namespace Surface\Contracts\Windows\Mail;

readonly class MenuToggled extends WindowEventOccurred implements WindowMail
{
    public function __construct(
        string $window,
        public readonly string $item,
        public readonly bool $on,
    ) {
        parent::__construct($window);
    }

    public function name(): string
    {
        return "menu.toggled.{$this->window}.{$this->item}";
    }
}