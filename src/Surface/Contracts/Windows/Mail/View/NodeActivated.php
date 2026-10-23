<?php

namespace Surface\Contracts\Windows\Mail\View;

/**
 * The user activated a tree node: double click, Enter or Return.
 */
readonly class NodeActivated extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public string $id,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return "view.node-activated.{$this->window}.{$this->path}";
    }
}
