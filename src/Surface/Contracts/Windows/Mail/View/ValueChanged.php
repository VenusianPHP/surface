<?php

namespace Surface\Contracts\Windows\Mail\View;

readonly class ValueChanged extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public readonly float $value,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return "view.value-changed.{$this->window}.{$this->path}";
    }
}
