<?php

declare(strict_types=1);

namespace Surface\Contracts\Windows\Mail\View;

/** Text the platform's input method committed to a focused canvas (dead keys and compose resolved). */
readonly class TextCommitted extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public string $text,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    public function name(): string
    {
        return "view.text-committed.{$this->window}.{$this->path}";
    }
}
