<?php

namespace Surface\NativeWindows\Components;

use Surface\Contracts\NativeWindows\Views\OSDropdown;
use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\NativeWindows\Windowable;

/**
 * A thin Component wrap over a dropdown. Options, selection, and the
 * select hook delegate to the inner dropdown.
 *
 * Parts: `dropdown`.
 */
class Select extends Component
{
    /**
     * @param list<string> $options
     */
    public function __construct(
        Windowable $window,
        string $name,
        int $x,
        int $y,
        int $width,
        int $height,
        protected array $options,
        protected int $selected = 0,
        ?OSGroup $in = null,
    ) {
        parent::__construct($window, $name, $x, $y, $width, $height, $in);
    }

    protected function build(): void
    {
        $this->register('dropdown', $this->root->dropdown(
            $this->partName('dropdown'),
            $this->options,
            $this->selected,
            0,
            0,
            1,
            1,
        ));
    }

    protected function layout(): void
    {
        [$width, $height] = $this->innerSize();
        $this->parts['dropdown']->place(0, 0, $width, $height);
    }

    /** The wrapped dropdown, for anything not delegated below. */
    public function dropdown(): OSDropdown
    {
        /** @var OSDropdown */
        return $this->parts['dropdown'];
    }

    /** @return list<string> */
    public function options(): array
    {
        return $this->dropdown()->options();
    }

    /** @param list<string> $options */
    public function setOptions(array $options, int $selected = 0): static
    {
        $this->dropdown()->setOptions($options, $selected);

        return $this;
    }

    public function selectedIndex(): int
    {
        return $this->dropdown()->selectedIndex();
    }

    public function selectedOption(): ?string
    {
        return $this->dropdown()->selectedOption();
    }

    public function select(int $index): static
    {
        $this->dropdown()->select($index);

        return $this;
    }

    public function onSelect(callable $hook): static
    {
        $this->dropdown()->onSelect($hook);

        return $this;
    }
}
