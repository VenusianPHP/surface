<?php

namespace Surface\Contracts\Windows\Mail\View;

/**
 * The user changed a tree's selection.
 */
readonly class NodeSelected extends PrimitiveEventOccurred implements PrimitiveMail
{
    /**
     * @param string|null $id the selected node, null = selection cleared
     */
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public ?string $id,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return "view.node-selected.{$this->window}.{$this->path}";
    }
}
