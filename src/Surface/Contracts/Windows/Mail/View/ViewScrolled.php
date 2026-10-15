<?php

namespace Surface\Contracts\Windows\Mail\View;

use Surface\Contracts\Windows\Styling\ScrollPosition;

/**
 * A watched view the user scrolled: wheel, scroll bar, keys, or a caret move that scrolled.
 */
readonly class ViewScrolled extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public ScrollPosition $position,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return "view.scrolled.{$this->window}.{$this->path}";
    }
}
