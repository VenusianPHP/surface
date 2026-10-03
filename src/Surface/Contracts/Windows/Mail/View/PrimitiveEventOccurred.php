<?php

namespace Surface\Contracts\Windows\Mail\View;

abstract readonly class PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        public string $window,
        public string $path,
        public string $uuid,
    ) {}

    /**
     * @return string
     */
    abstract public function name(): string;

    /**
     * @return string
     */
    public function window(): string
    {
        return $this->window;
    }

    /**
     * @return string
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function uuid(): string
    {
        return $this->uuid;
    }
}
