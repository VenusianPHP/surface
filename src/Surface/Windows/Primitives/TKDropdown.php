<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKDropdown as PrimitiveContract;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\WindowException;

/**
 * selected is -1 exactly when there are no options. The driver posts SelectionChanged
 * from the native signal, right after nativeSelected().
 */
abstract class TKDropdown extends TKPrimitive implements PrimitiveContract
{
    /**
     * @var list<string>
     */
    protected array $options;

    protected int $selected;

    /**
     * @param list<string> $options
     * @param int $selected ignored when there are no options
     * @throws WindowException When the name is not valid, the options are not a list of strings, or $selected is out of range.
     */
    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        array $options,
        int $selected,
    ) {
        $this->options = self::guardOptions($options);
        $this->selected = $options === [] ? -1 : $this->guardIndex($selected);
        parent::__construct($name, $window, $parent, $placement);
    }

    public function options(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): static
    {
        $this->live();
        $this->options = self::guardOptions($options);
        $this->selected = match (true) {
            $options === [] => -1,
            $this->selected >= 0 && $this->selected < count($options) => $this->selected,
            default => 0,
        };
        $this->applyOptions($this->options);
        $this->applySelected($this->selected);

        return $this;
    }

    public function selected(): int
    {
        return $this->selected;
    }

    public function select(int $index): static
    {
        $this->live();
        $this->selected = $this->guardIndex($index);
        $this->applySelected($index);

        return $this;
    }

    public function selectedOption(): ?string
    {
        return $this->options[$this->selected] ?? null;
    }

    /**
     * Engine callback: record the user's choice, post nothing, write nothing back.
     *
     * @param int $index
     * @return void
     */
    public function nativeSelected(int $index): void
    {
        $this->selected = $index;
    }

    /**
     * @param array<mixed> $options
     * @return list<string>
     * @throws WindowException
     */
    protected static function guardOptions(array $options): array
    {
        if (! array_is_list($options) || array_filter($options, fn (mixed $option): bool => ! is_string($option)) !== []) {
            throw new WindowException('Dropdown options must be a list of strings.');
        }

        return $options;
    }

    /**
     * @param int $index
     * @return int
     * @throws WindowException When outside the options.
     */
    protected function guardIndex(int $index): int
    {
        if ($index < 0 || $index >= count($this->options)) {
            throw new WindowException("Dropdown index {$index} is out of range (".count($this->options).' options).');
        }

        return $index;
    }

    /**
     * Replace the native's items. applySelected() follows with the kept selection.
     *
     * @param list<string> $options
     * @return void
     */
    abstract protected function applyOptions(array $options): void;

    /**
     * @param int $index -1 = nothing selected (no options)
     * @return void
     */
    abstract protected function applySelected(int $index): void;
}
