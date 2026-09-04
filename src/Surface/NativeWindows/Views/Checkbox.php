<?php

namespace Surface\NativeWindows\Views;

use Closure;
use Surface\Contracts\Core\Events\SurfaceEventType;
use Surface\Contracts\NativeWindows\Views\OSCheckbox;
use Surface\NativeWindows\Enums\SizeRule;
use Surface\NativeWindows\Windowable;

/**
 * A labelled checkbox. Engines wire their native toggle into
 * fireToggled(); every tick rides the dock as TOGGLED
 * (`<window>.<name>.toggled`).
 */
abstract class Checkbox extends View implements OSCheckbox
{
    use HasEnabledState;
    use StylesText;

    protected ?Closure $on_toggle = null;

    public function __construct(
        string $name,
        Windowable $window,
        protected string $box_label,
        protected bool $checked,
    ) {
        parent::__construct($name, $window);
    }

    public function label(): ?string
    {
        return $this->box_label;
    }

    public function setLabel(string $label): static
    {
        $this->box_label = $label;
        $this->applyLabel($label);

        if ($this->sizing === SizeRule::NATURAL) {
            $this->relayout();
        }

        return $this;
    }

    public function isChecked(): bool
    {
        return $this->checked;
    }

    public function setChecked(bool $checked): static
    {
        if ($this->checked !== $checked) {
            $this->checked = $checked;
            $this->applyChecked($checked);
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
    protected function fireToggled(bool $checked): void
    {
        $this->checked = $checked;
        $this->window->emitViewEvent(SurfaceEventType::TOGGLED, $this->name, ['on' => $checked]);

        if (! is_null($this->on_toggle)) {
            ($this->on_toggle)($checked);
        }
    }

    /**
     * Write the label to the native control.
     * @return void
     */
    abstract protected function applyLabel(string $label): void;

    /**
     * Write the checked state to the native control.
     * @return void
     */
    abstract protected function applyChecked(bool $checked): void;
}
