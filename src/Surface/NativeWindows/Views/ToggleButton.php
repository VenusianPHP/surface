<?php

namespace Surface\NativeWindows\Views;

use Closure;
use Surface\Contracts\Core\Events\SurfaceEventType;
use Surface\Contracts\NativeWindows\Views\OSToggleButton;
use Surface\NativeWindows\Enums\SizeRule;
use Surface\NativeWindows\Windowable;

/**
 * A button that stays pressed until pressed again. Engines wire their
 * native toggle into fireToggled(); every press rides the dock as TOGGLED
 * (`<window>.<name>.toggled`).
 */
abstract class ToggleButton extends View implements OSToggleButton
{
    use HasEnabledState;
    use StylesText;

    protected ?Closure $on_toggle = null;

    public function __construct(
        string $name,
        Windowable $window,
        protected string $button_label,
        protected bool $pressed,
    ) {
        parent::__construct($name, $window);
    }

    public function label(): ?string
    {
        return $this->button_label;
    }

    public function setLabel(string $label): static
    {
        $this->button_label = $label;
        $this->applyLabel($label);

        if ($this->sizing === SizeRule::NATURAL) {
            $this->relayout();
        }

        return $this;
    }

    public function isPressed(): bool
    {
        return $this->pressed;
    }

    public function setPressed(bool $pressed): static
    {
        if ($this->pressed !== $pressed) {
            $this->pressed = $pressed;
            $this->applyPressed($pressed);
        }

        return $this;
    }

    public function onToggle(callable $hook): static
    {
        $this->on_toggle = $hook(...);

        return $this;
    }

    /**
     * Engine callbacks land here with the pressed state read from the
     * native control. Pushes TOGGLED, then invokes the hook; safe with no
     * hook and no sink.
     * @return void
     */
    protected function fireToggled(bool $pressed): void
    {
        $this->pressed = $pressed;
        $this->window->emitViewEvent(SurfaceEventType::TOGGLED, $this->name, ['on' => $pressed]);

        if (! is_null($this->on_toggle)) {
            ($this->on_toggle)($pressed);
        }
    }

    /**
     * Write the label to the native control.
     * @return void
     */
    abstract protected function applyLabel(string $label): void;

    /**
     * Write the pressed state to the native control.
     * @return void
     */
    abstract protected function applyPressed(bool $pressed): void;
}
