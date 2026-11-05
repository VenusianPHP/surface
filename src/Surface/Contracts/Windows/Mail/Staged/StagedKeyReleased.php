<?php

namespace Surface\Contracts\Windows\Mail\Staged;

use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\Modifiers;
use Surface\Contracts\Windows\Mail\WindowEventOccurred;
use Surface\Contracts\Windows\Mail\WindowMail;

/**
 * A key went up while the window has key focus. Posted only while watchInput() is on, for user action; the twin of View\KeyReleased.
 */
readonly class StagedKeyReleased extends WindowEventOccurred implements WindowMail
{
    public function __construct(
        string $window,
        public Key $key,
        public Modifiers $modifiers,
    ) {
        parent::__construct($window);
    }

    public function name(): string
    {
        return "staged.key-released.{$this->window}";
    }
}
