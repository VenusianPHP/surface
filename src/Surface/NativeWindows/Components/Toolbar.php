<?php

namespace Surface\NativeWindows\Components;

use Surface\Contracts\NativeWindows\Views\OSButton;
use Surface\Contracts\NativeWindows\Views\OSToggleButton;
use Surface\Contracts\NativeWindows\WindowableException;

/**
 * A horizontal strip of actions flowed left to right: buttons, toggle
 * buttons, and separators, vertically centred, natural widths.
 *
 * Items are added after construction and every add re-flows; the sketch
 * hooks each item as it lands (`$toolbar->addButton('save', 'Save',
 * fn () => ...)`). Parts carry the item names; separators are
 * auto-named `sep.<n>`.
 */
class Toolbar extends Component
{
    protected const PAD = 8;

    protected const GAP = 8;

    /** @var list<string> Item part names, in flow order. */
    protected array $flow = [];

    protected int $separators = 0;

    protected function build(): void
    {
        // A toolbar starts empty — items flow in through the add methods.
    }

    /** A momentary action. The hook runs in-pump, the Button's own rule. */
    public function addButton(string $name, string $label, ?callable $hook = null): OSButton
    {
        $this->guardItem($name);

        $button = $this->root->button($this->partName($name), $label, 0, 0, 1, 1);
        if (! is_null($hook)) {
            $button->onClick($hook);
        }

        $this->register($name, $button);
        $this->flow[] = $name;
        $this->layout();

        return $button;
    }

    /** A press-and-stay action. */
    public function addToggle(string $name, string $label, bool $pressed = false): OSToggleButton
    {
        $this->guardItem($name);

        $toggle = $this->root->toggleButton($this->partName($name), $label, $pressed, 0, 0, 1, 1);

        $this->register($name, $toggle);
        $this->flow[] = $name;
        $this->layout();

        return $toggle;
    }

    /** A vertical dividing line between item groups. */
    public function addSeparator(): static
    {
        $name = 'sep.'.(++$this->separators);
        [, $height] = $this->innerSize();

        $separator = $this->root->separator($this->partName($name), 0, self::PAD, 1, max(1, $height - 2 * self::PAD));

        $this->register($name, $separator);
        $this->flow[] = $name;
        $this->layout();

        return $this;
    }

    protected function layout(): void
    {
        [, $height] = $this->innerSize();
        $x = self::PAD;

        foreach ($this->flow as $name) {
            $view = $this->parts[$name];

            if (str_starts_with($name, 'sep.')) {
                $view->place($x, self::PAD, 1, max(1, $height - 2 * self::PAD));
                $x += 1 + self::GAP;
                continue;
            }

            // Natural size, then centre the item on the strip's axis.
            $view->hug();
            $frame = $view->frame();
            $view->place($x, max(0, (int) floor(($height - $frame['height']) / 2)), $frame['width'], $frame['height']);
            $x += $frame['width'] + self::GAP;
        }
    }

    protected function guardItem(string $name): void
    {
        if (isset($this->parts[$name])) {
            throw new WindowableException("Toolbar '{$this->name}' already has an item '{$name}'.");
        }
    }
}
