<?php

namespace Surface\Contracts\Windows\Mail;

use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\Modifiers;

/**
 * A key press no focused native took and no menu accelerator, default or cancel button, or Tab
 * move answered. key is the physical key; character the active layout's text for it, '' for none.
 */
readonly class WindowKeyPressed extends WindowEventOccurred implements WindowMail
{
    public function __construct(
        string $window,
        public Key $key,
        public string $character,
        public Modifiers $modifiers,
        public bool $repeat,
    ) {
        parent::__construct($window);
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return "window.key.{$this->window}";
    }
}
