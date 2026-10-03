<?php

namespace Surface\Contracts\Windows\Mail\View;

readonly class VideoFailed extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public readonly string $reason,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return "view.video-failed.{$this->window}.{$this->path}";
    }
}
