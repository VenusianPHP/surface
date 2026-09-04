<?php

namespace Surface\NativeWindows\Components;

use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\Contracts\NativeWindows\Views\OSTextInput;
use Surface\NativeWindows\Windowable;

/**
 * A text input with an icon glyph at its left — the icon is a string
 * (emoji or symbol), the one icon path both engines render identically
 * in a label today.
 *
 * Parts: `icon`, `input`. The field API delegates to the inner input, so
 * a sketch treats the component like the input it wraps.
 */
class IconField extends Component
{
    protected const GAP = 6;

    protected const ICON_SIZE = 22;

    public function __construct(
        Windowable $window,
        string $name,
        int $x,
        int $y,
        int $width,
        int $height,
        protected string $icon,
        protected string $value = '',
        protected ?string $placeholder = null,
        ?OSGroup $in = null,
    ) {
        parent::__construct($window, $name, $x, $y, $width, $height, $in);
    }

    protected function build(): void
    {
        $this->register('icon', $this->root->label($this->partName('icon'), $this->icon, 0, 0, 1, 1));
        $this->register('input', $this->root->textInput(
            $this->partName('input'),
            $this->value,
            0,
            0,
            1,
            1,
            placeholder: $this->placeholder,
        ));
    }

    protected function layout(): void
    {
        [$width, $height] = $this->innerSize();

        $this->parts['icon']->place(
            0,
            max(0, (int) floor(($height - self::ICON_SIZE) / 2)),
            self::ICON_SIZE,
            self::ICON_SIZE,
        );

        $this->parts['input']->place(
            self::ICON_SIZE + self::GAP,
            0,
            max(0, $width - self::ICON_SIZE - self::GAP),
            $height,
        );
    }

    /** The wrapped input, for anything not delegated below. */
    public function input(): OSTextInput
    {
        /** @var OSTextInput */
        return $this->parts['input'];
    }

    public function value(): string
    {
        return $this->input()->value();
    }

    public function setValue(string $value): static
    {
        $this->input()->setValue($value);

        return $this;
    }

    public function onChange(callable $hook): static
    {
        $this->input()->onChange($hook);

        return $this;
    }

    public function onSubmit(callable $hook): static
    {
        $this->input()->onSubmit($hook);

        return $this;
    }
}
