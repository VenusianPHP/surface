<?php

namespace Surface\Contracts\Windows\Mail\View;

readonly class Toggled extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public readonly bool $on,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return "view.toggled.{$this->window}.{$this->path}";
    }
}
