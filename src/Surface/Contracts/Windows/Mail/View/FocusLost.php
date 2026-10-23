<?php

namespace Surface\Contracts\Windows\Mail\View;

/** The user moved keyboard focus away from this primitive's native. */
readonly class FocusLost extends PrimitiveEventOccurred implements PrimitiveMail
{
    /**
     * @return string
     */
    public function name(): string
    {
        return "view.focus-lost.{$this->window}.{$this->path}";
    }
}
