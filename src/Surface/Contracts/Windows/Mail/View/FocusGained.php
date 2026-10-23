<?php

namespace Surface\Contracts\Windows\Mail\View;

/** The user moved keyboard focus to this primitive's native: a click, Tab, or the toolkit's own move. */
readonly class FocusGained extends PrimitiveEventOccurred implements PrimitiveMail
{
    /**
     * @return string
     */
    public function name(): string
    {
        return "view.focus-gained.{$this->window}.{$this->path}";
    }
}
