<?php

declare(strict_types=1);

namespace Surface\Contracts\Windows\Mail\View;

use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\Modifiers;

/** A key came up on a focused canvas. */
readonly class KeyReleased extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public Key $key,
        public Modifiers $modifiers,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    public function name(): string
    {
        return "view.key-released.{$this->window}.{$this->path}";
    }
}
