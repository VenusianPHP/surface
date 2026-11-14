<?php

namespace Surface\Contracts\Windows\Mail\View;

/**
 * The user clicked an enabled item's action button in an item view.
 */
readonly class ItemActionClicked extends PrimitiveEventOccurred implements PrimitiveMail
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
        return "view.item-action-clicked.{$this->window}.{$this->path}";
    }
}
