<?php

namespace Surface\Contracts\Windows\Mail\View;

/** A web view could not load a page; $reason is the engine's. */
readonly class WebViewFailed extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public readonly ?string $url,
        public readonly string $reason,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return "view.web-view-failed.{$this->window}.{$this->path}";
    }
}
