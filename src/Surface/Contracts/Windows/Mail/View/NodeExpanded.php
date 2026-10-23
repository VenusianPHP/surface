<?php

namespace Surface\Contracts\Windows\Mail\View;

/**
 * The user expanded or collapsed a tree node.
 */
readonly class NodeExpanded extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public string $id,
        public bool $expanded,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return "view.node-expanded.{$this->window}.{$this->path}";
    }
}
