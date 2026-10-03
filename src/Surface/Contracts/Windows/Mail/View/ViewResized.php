<?php

namespace Surface\Contracts\Windows\Mail\View;

readonly class ViewResized extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public readonly int $width,
        public readonly int $height,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return "view.resized.{$this->window}.{$this->path}";
    }
}
