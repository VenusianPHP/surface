<?php

namespace Surface\Contracts\Windows\Mail\View;

readonly class VideoEnded extends PrimitiveEventOccurred implements PrimitiveMail
{
    /**
     * @return string
     */
    public function name(): string
    {
        return "view.video-ended.{$this->window}.{$this->path}";
    }
}
