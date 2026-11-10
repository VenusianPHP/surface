<?php

namespace Surface\Contracts\Windows\Mail\View;

/**
 * The user clicked a clickable group (TKPrimitiveGroup::setClickable): a primary press and its release
 * on the group or on passive content inside it. x and y of the release, from the group's top-left.
 */
readonly class GroupClicked extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public float $x,
        public float $y,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    public function name(): string
    {
        return "view.group-clicked.{$this->window}.{$this->path}";
    }
}
