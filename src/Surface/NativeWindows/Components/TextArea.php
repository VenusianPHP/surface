<?php

namespace Surface\NativeWindows\Components;

use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\Contracts\NativeWindows\Views\OSTextArea;
use Surface\NativeWindows\Windowable;

/**
 * A thin Component wrap over a multi-line text editor. The field API
 * delegates to the inner area.
 *
 * Parts: `area`.
 */
class TextArea extends Component
{
    public function __construct(
        Windowable $window,
        string $name,
        int $x,
        int $y,
        int $width,
        int $height,
        protected string $value = '',
        ?OSGroup $in = null,
    ) {
        parent::__construct($window, $name, $x, $y, $width, $height, $in);
    }

    protected function build(): void
    {
        $this->register('area', $this->root->textArea(
            $this->partName('area'),
            $this->value,
            0,
            0,
            1,
            1,
        ));
    }

    protected function layout(): void
    {
        [$width, $height] = $this->innerSize();
        $this->parts['area']->place(0, 0, $width, $height);
    }

    /** The wrapped area, for anything not delegated below. */
    public function area(): OSTextArea
    {
        /** @var OSTextArea */
        return $this->parts['area'];
    }

    public function value(): string
    {
        return $this->area()->value();
    }

    public function setValue(string $value): static
    {
        $this->area()->setValue($value);

        return $this;
    }

    public function setEditable(bool $editable): static
    {
        $this->area()->setEditable($editable);

        return $this;
    }

    public function onChange(callable $hook): static
    {
        $this->area()->onChange($hook);

        return $this;
    }
}
