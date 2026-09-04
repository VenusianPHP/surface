<?php

namespace Surface\NativeWindows\Components;

use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\Contracts\NativeWindows\Views\OSToggle;
use Surface\NativeWindows\Windowable;

/**
 * A thin Component wrap over an on/off toggle. State and the toggle
 * hook delegate to the inner switch.
 *
 * Parts: `switch`.
 */
class ToggleSwitch extends Component
{
    public function __construct(
        Windowable $window,
        string $name,
        int $x,
        int $y,
        int $width,
        int $height,
        protected bool $on = false,
        ?OSGroup $in = null,
    ) {
        parent::__construct($window, $name, $x, $y, $width, $height, $in);
    }

    protected function build(): void
    {
        $this->register('switch', $this->root->toggle(
            $this->partName('switch'),
            $this->on,
            0,
            0,
            1,
            1,
        ));
    }

    protected function layout(): void
    {
        [$width, $height] = $this->innerSize();
        $this->parts['switch']->place(0, 0, $width, $height);
    }

    /** The wrapped toggle, for anything not delegated below. */
    public function toggle(): OSToggle
    {
        /** @var OSToggle */
        return $this->parts['switch'];
    }

    public function isOn(): bool
    {
        return $this->toggle()->isOn();
    }

    public function setOn(bool $on): static
    {
        $this->toggle()->setOn($on);

        return $this;
    }

    public function onToggle(callable $hook): static
    {
        $this->toggle()->onToggle($hook);

        return $this;
    }
}
