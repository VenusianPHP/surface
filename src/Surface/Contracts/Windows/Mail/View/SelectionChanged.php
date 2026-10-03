<?php

namespace Surface\Contracts\Windows\Mail\View;

readonly class SelectionChanged extends PrimitiveEventOccurred implements PrimitiveMail
{
    /**
     * @param int $index -1 when there are no options
     * @param string|null $option
     */
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public readonly int $index,
        public readonly ?string $option,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return "view.selection-changed.{$this->window}.{$this->path}";
    }
}
