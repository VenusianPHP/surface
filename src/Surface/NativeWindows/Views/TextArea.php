<?php

namespace Surface\NativeWindows\Views;

use Closure;
use Surface\Contracts\Core\Events\SurfaceEventType;
use Surface\Contracts\NativeWindows\Views\OSTextArea;
use Surface\NativeWindows\Windowable;

/**
 * A multi-line, scrolling text editor. Holds the value Surface believes
 * in; engines wire their native buffer-changed signal into fireChanged()
 * with the text read back from their buffer, so value() is always what
 * the engine holds.
 */
abstract class TextArea extends View implements OSTextArea
{
    use StylesText;

    protected ?Closure $on_change = null;

    protected bool $editable = true;

    public function __construct(
        string $name,
        Windowable $window,
        protected string $value,
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

    public function isEditable(): bool
    {
        return $this->editable;
    }

    public function setEditable(bool $editable): static
    {
        if ($this->editable !== $editable) {
            $this->editable = $editable;
            $this->applyEditable($editable);
        }

        return $this;
    }

    public function onChange(callable $hook): static
    {
        $this->on_change = $hook(...);

        return $this;
    }

    /**
     * Engine callbacks land here with the value read from the native
     * buffer. Pushes TEXT_CHANGED, then invokes the hook; safe with no
     * hook and no sink.
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
     * Write the text to the native buffer.
     * @return void
     */
    abstract protected function applyValue(string $value): void;

    /**
     * Write the editable state to the native editor.
     * @return void
     */
    abstract protected function applyEditable(bool $editable): void;
}
