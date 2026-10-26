<?php

namespace Surface\Contracts\Windows\Mail\View;

/**
 * The user moved a scroll bar: dragging its thumb (live during the drag), a click in its track, or
 * a wheel over it. value runs 0..max(0, maximum - page). Latest wins.
 */
readonly class ScrollChanged extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public int $value,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    public function name(): string
    {
        return "view.scroll-changed.{$this->window}.{$this->path}";
    }
}
