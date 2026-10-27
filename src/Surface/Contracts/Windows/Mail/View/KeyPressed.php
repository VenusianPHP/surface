<?php

declare(strict_types=1);

namespace Surface\Contracts\Windows\Mail\View;

use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\Modifiers;

/**
 * A key went down on a focused canvas. Key is the physical key; character is the layout's text for
 * it, '' for none. EditCommanded or TextCommitted may follow for the same press; neither posts for a
 * key the canvas declined (Tab on a canvas that does not take it).
 */
readonly class KeyPressed extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public Key $key,
        public string $character,
        public Modifiers $modifiers,
        public bool $repeat,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    public function name(): string
    {
        return "view.key-pressed.{$this->window}.{$this->path}";
    }
}
