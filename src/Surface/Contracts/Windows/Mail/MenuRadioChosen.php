<?php

namespace Surface\Contracts\Windows\Mail;

/** The user chose another item of a radio group, in the bar or a context menu. */
readonly class MenuRadioChosen extends WindowEventOccurred implements WindowMail
{
    public function __construct(
        string $window,
        public readonly string $group,
        public readonly string $item,
    ) {
        parent::__construct($window);
    }

    public function name(): string
    {
        return "menu.radio.{$this->window}.{$this->group}";
    }
}
