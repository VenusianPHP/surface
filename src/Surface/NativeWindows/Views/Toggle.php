<?php

namespace Surface\NativeWindows\Views;

use Closure;
use Surface\Contracts\Core\Events\SurfaceEventType;
use Surface\Contracts\NativeWindows\Views\OSToggle;
use Surface\NativeWindows\Windowable;

/**
 * An on/off switch. Engines wire their native flip into fireToggled();
 * every flip rides the dock as TOGGLED (`<window>.<name>.toggled`).
 *
 * (The class would be called Switch if PHP allowed it.)
 */
abstract class Toggle extends View implements OSToggle
{
    use HasEnabledState;

    protected ?Closure $on_toggle = null;

    public function __construct(
        string $name,
        Windowable $window,
        protected bool $on,
    ) {
        parent::__construct($name, $window);
    }

    public function isOn(): bool
    {
        return $this->on;
    }

    public function setOn(bool $on): static
    {
        if ($this->on !== $on) {
            $this->on = $on;
            $this->applyOn($on);
        }

        return $this;
    }

    public function onToggle(callable $hook): static
    {
        $this->on_toggle = $hook(...);

        return $this;
    }

    /**
     * Engine callbacks land here with the state read from the native
     * control. Pushes TOGGLED, then invokes the hook; safe with no hook
     * and no sink.
     * @return void
     */
    protected function fireToggled(bool $on): void
    {
        $this->on = $on;
        $this->window->emitViewEvent(SurfaceEventType::TOGGLED, $this->name, ['on' => $on]);

        if (! is_null($this->on_toggle)) {
            ($this->on_toggle)($on);
        }
    }

    /**
     * Write the state to the native control.
     * @return void
     */
    abstract protected function applyOn(bool $on): void;
}
