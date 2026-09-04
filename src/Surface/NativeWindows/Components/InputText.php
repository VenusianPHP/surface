<?php

namespace Surface\NativeWindows\Components;

use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\Contracts\NativeWindows\Views\OSTextInput;
use Surface\NativeWindows\Windowable;

/**
 * A thin Component wrap over a single-line text input. The field API
 * delegates to the inner input, so a sketch treats the component like
 * the primitive it wraps.
 *
 * Parts: `input`.
 */
class InputText extends Component
{
    public function __construct(
        Windowable $window,
        string $name,
        int $x,
        int $y,
        int $width,
        int $height,
        protected string $value = '',
        protected ?string $placeholder = null,
        ?OSGroup $in = null,
    ) {
        parent::__construct($window, $name, $x, $y, $width, $height, $in);
    }

    protected function build(): void
    {
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
        $this->parts['input']->place(0, 0, $width, $height);
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
