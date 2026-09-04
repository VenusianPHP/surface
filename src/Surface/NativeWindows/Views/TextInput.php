<?php

namespace Surface\NativeWindows\Views;

use Closure;
use Surface\Contracts\Core\Events\SurfaceEventType;
use Surface\Contracts\NativeWindows\Views\OSTextInput;
use Surface\NativeWindows\Windowable;

/**
 * A single-line text field. Holds the value Surface believes in; engines
 * wire their native edit signal into fireChanged() and their submit
 * (Enter) into fireSubmitted().
 *
 * Every edit rides the dock as TEXT_CHANGED (`<window>.<name>.changed`)
 * and every submit as TEXT_SUBMITTED (`<window>.<name>.submitted`). The
 * hooks run inside the pump that delivered the keystroke — the same
 * deliberate exception Button documents for view hooks.
 *
 * A secret field masks its glyphs. An engine with no honest placeholder
 * path for a secret field ignores the placeholder, stated in its own code.
 */
abstract class TextInput extends View implements OSTextInput
{
    use HasEnabledState;
    use StylesText;

    protected ?Closure $on_change = null;

    protected ?Closure $on_submit = null;

    public function __construct(
        string $name,
        Windowable $window,
        protected string $value,
        protected ?string $placeholder = null,
        public readonly bool $secret = false,
    ) {
        parent::__construct($name, $window);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;
        $this->applyValue($value);

        return $this;
    }

    public function placeholder(): ?string
    {
        return $this->placeholder;
    }

    public function setPlaceholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;
        $this->applyPlaceholder($placeholder);

        return $this;
    }

    public function onChange(callable $hook): static
    {
        $this->on_change = $hook(...);

        return $this;
    }

    public function onSubmit(callable $hook): static
    {
        $this->on_submit = $hook(...);

        return $this;
    }

    /**
     * Engine callbacks land here with the value read from the native
     * field. Pushes TEXT_CHANGED, then invokes the hook; safe with no hook
     * and no sink.
     * @return void
     */
    protected function fireChanged(string $value): void
    {
        $this->value = $value;
        $this->window->emitViewEvent(SurfaceEventType::TEXT_CHANGED, $this->name, ['value' => $value]);

        if (! is_null($this->on_change)) {
            ($this->on_change)($value);
        }
    }

    /**
     * Engine submit callbacks (Enter) land here. Pushes TEXT_SUBMITTED
     * with the current value, then invokes the hook.
     * @return void
     */
    protected function fireSubmitted(): void
    {
        $this->window->emitViewEvent(SurfaceEventType::TEXT_SUBMITTED, $this->name, ['value' => $this->value]);

        if (! is_null($this->on_submit)) {
            ($this->on_submit)($this->value);
        }
    }

    /**
     * Write the value to the native field.
     * @return void
     */
    abstract protected function applyValue(string $value): void;

    /**
     * Write the placeholder to the native field.
     * @return void
     */
    abstract protected function applyPlaceholder(string $placeholder): void;
}
