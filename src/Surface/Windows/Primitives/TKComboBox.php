<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKComboBox as PrimitiveContract;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\WindowException;

/**
 * The driver posts TextChanged, TextSubmitted and SelectionChanged from the native, right after
 * the matching engine callback; nothing here posts.
 */
abstract class TKComboBox extends TKPrimitive implements PrimitiveContract
{
    /**
     * @var list<string>
     */
    protected array $options;

    /**
     * @var list<string>
     */
    protected array $completions = [];

    /**
     * @param list<string> $options
     * @throws WindowException When the name is not valid or the options are not a list of strings.
     */
    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        protected string $value,
        array $options,
    ) {
        $this->options = self::guardList($options, 'options');
        parent::__construct($name, $window, $parent, $placement);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->live();
        $this->value = $value;
        $this->completions = [];
        $this->applyValue($value);

        return $this;
    }

    public function options(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): static
    {
        $this->live();
        $this->options = self::guardList($options, 'options');
        $this->applyOptions($this->options);

        return $this;
    }

    public function completions(): array
    {
        return $this->completions;
    }

    public function setCompletions(array $completions): static
    {
        $this->live();
        $completions = self::guardList($completions, 'completions');
        $this->completions = $this->applyCompletions($completions) ? $completions : [];

        return $this;
    }

    /**
     * Engine callback: the user typed; record the value, post nothing.
     *
     * @param string $value
     * @return void
     */
    public function nativeEdited(string $value): void
    {
        $this->value = $value;
    }

    /**
     * Engine callback: the user chose an option or a completion; the list is hidden.
     *
     * @param string $value
     * @return void
     */
    public function nativeChose(string $value): void
    {
        $this->value = $value;
        $this->completions = [];
    }

    /**
     * Engine callback: the native hid the completion list (Esc, focus leaving the field).
     *
     * @return void
     */
    public function nativeHidden(): void
    {
        $this->completions = [];
    }

    /**
     * @param array<mixed> $items
     * @param string $what
     * @return list<string>
     * @throws WindowException
     */
    protected static function guardList(array $items, string $what): array
    {
        if (! array_is_list($items) || array_filter($items, fn (mixed $item): bool => ! is_string($item)) !== []) {
            throw new WindowException("Combo {$what} must be a list of strings.");
        }

        return $items;
    }

    /**
     * Write the field's text and hide a shown completion list.
     *
     * @param string $value
     * @return void
     */
    abstract protected function applyValue(string $value): void;

    /**
     * @param list<string> $options
     * @return void
     */
    abstract protected function applyOptions(array $options): void;

    /**
     * Show the completions under the field, or hide the list for []. Returns false, showing
     * nothing, when the field has no focus.
     *
     * @param list<string> $completions
     * @return bool
     */
    abstract protected function applyCompletions(array $completions): bool;
}
