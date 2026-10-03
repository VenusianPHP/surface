<?php

namespace Surface\Contracts\Windows\Mail\View;

readonly class VideoPlaying extends PrimitiveEventOccurred implements PrimitiveMail
{
    /**
     * @return string
     */
    public function name(): string
    {
        return "view.video-playing.{$this->window}.{$this->path}";
    }
}
