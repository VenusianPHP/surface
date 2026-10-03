<?php

namespace Surface\Contracts\Windows\Mail\View;

readonly class VideoPaused extends PrimitiveEventOccurred implements PrimitiveMail
{
    /**
     * @return string
     */
    public function name(): string
    {
        return "view.video-paused.{$this->window}.{$this->path}";
    }
}
