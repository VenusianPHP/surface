<?php

namespace Surface\Contracts\Windows\Mail\Staged;

use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\Modifiers;
use Surface\Contracts\Windows\Mail\WindowEventOccurred;
use Surface\Contracts\Windows\Mail\WindowMail;

/**
 * A key went down while the window has key focus. Posted only while watchInput() is on, for user action; the twin of View\KeyPressed.
 */
readonly class StagedKeyPressed extends WindowEventOccurred implements WindowMail
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

    public function name(): string
    {
        return "staged.key-pressed.{$this->window}";
    }
}
