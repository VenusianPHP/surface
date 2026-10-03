<?php

namespace Surface\Contracts\Windows\Mail\View;

use DateTimeImmutable;

readonly class DateChanged extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public readonly DateTimeImmutable $date,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return "view.date-changed.{$this->window}.{$this->path}";
    }
}
