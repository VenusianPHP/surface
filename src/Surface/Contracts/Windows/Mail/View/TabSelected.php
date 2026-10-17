<?php

namespace Surface\Contracts\Windows\Mail\View;

/**
 * The user switched a tab view to another page.
 */
readonly class TabSelected extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public string $page,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return "view.tab-selected.{$this->window}.{$this->path}";
    }
}
