<?php

namespace Surface\NativeWindows\Views;

use Closure;
use Surface\Contracts\Core\Events\SurfaceEventType;
use Surface\Contracts\NativeWindows\Views\OSDropdown;
use Surface\NativeWindows\Windowable;

/**
 * A closed list of options, one selected. Engines wire their native
 * selection change into fireSelected(); every pick rides the dock as
 * SELECTION_CHANGED (`<window>.<name>.selected`).
 */
abstract class Dropdown extends View implements OSDropdown
{
    use HasEnabledState;

    protected ?Closure $on_select = null;

    /**
     * @param list<string> $options
     */
    public function __construct(
        string $name,
        Windowable $window,
        protected array $options,
        protected int $selected,
    ) {
        parent::__construct($name, $window);
        $this->selected = $this->clampIndex($selected);
    }

    public function options(): array
    {
        return $this->options;
    }

    public function setOptions(array $options, int $selected = 0): static
    {
        $this->options = array_values($options);
        $this->selected = $this->clampIndex($selected);
        $this->applyOptions($this->options, $this->selected);

        return $this;
    }

    public function selectedIndex(): int
    {
        return $this->selected;
    }

    public function selectedOption(): ?string
    {
        return $this->options[$this->selected] ?? null;
    }

    public function select(int $index): static
    {
        $index = $this->clampIndex($index);
        if ($this->selected !== $index) {
            $this->selected = $index;
            $this->applySelected($index);
        }

        return $this;
    }

    public function onSelect(callable $hook): static
    {
        $this->on_select = $hook(...);

        return $this;
    }

    /**
     * Engine callbacks land here with the index read from the native
     * control. Pushes SELECTION_CHANGED, then invokes the hook with
     * (index, option); safe with no hook and no sink.
     * @return void
     */
    protected function fireSelected(int $index): void
    {
        $this->selected = $this->clampIndex($index);
        $option = $this->selectedOption();
        $this->window->emitViewEvent(SurfaceEventType::SELECTION_CHANGED, $this->name, [
            'index' => $this->selected,
            'option' => $option,
        ]);

        if (! is_null($this->on_select)) {
            ($this->on_select)($this->selected, $option);
        }
    }

    protected function clampIndex(int $index): int
    {
        if ($this->options === []) {
            return -1;
        }

        return max(0, min(count($this->options) - 1, $index));
    }

    /**
     * Rebuild the native option list and select one entry.
     * @param list<string> $options
     * @return void
     */
    abstract protected function applyOptions(array $options, int $selected): void;

    /**
     * Write the selection to the native control.
     * @return void
     */
    abstract protected function applySelected(int $selected): void;
}
