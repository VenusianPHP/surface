<?php

namespace Surface\Contracts\Windows\Mail\View;

readonly class TextChanged extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public readonly string $value,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return "view.text-changed.{$this->window}.{$this->path}";
    }
}
