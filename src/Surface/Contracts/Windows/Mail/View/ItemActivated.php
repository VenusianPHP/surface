<?php

namespace Surface\Contracts\Windows\Mail\View;

/**
 * The user activated an enabled item of an item view: a click on a collection cell; a double
 * click, Enter or Return on a list row.
 */
readonly class ItemActivated extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public int $index,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return "view.item-activated.{$this->window}.{$this->path}";
    }
}
