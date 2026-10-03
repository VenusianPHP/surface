<?php

namespace Surface\Contracts\Windows\Mail\View;

readonly class ButtonClicked extends PrimitiveEventOccurred implements PrimitiveMail
{
    /**
     * @return string
     */
    public function name(): string
    {
        return "view.clicked.{$this->window}.{$this->path}";
    }
}
