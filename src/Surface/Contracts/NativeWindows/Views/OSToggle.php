<?php

namespace Surface\Contracts\NativeWindows\Views;

/**
 * An on/off switch.
 */
interface OSToggle extends OSView
{
    public function isOn(): bool;

    public function setOn(bool $on): static;

    /** Hook invoked when the user flips it, during the pump. Receives the new state. */
    public function onToggle(callable $hook): static;

    public function setEnabled(bool $enabled): static;

    public function isEnabled(): bool;
}
