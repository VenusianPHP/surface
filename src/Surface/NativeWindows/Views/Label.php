<?php

namespace Surface\NativeWindows\Views;

use Surface\Contracts\NativeWindows\Views\OSLabel;
use Surface\Contracts\NativeWindows\Views\TextAlignment;
use Surface\NativeWindows\Windowable;

/**
 * Static text. Holds the text and alignment Surface believes in; engines
 * translate through the two hooks.
 */
abstract class Label extends View implements OSLabel
{
    use StylesText;

    protected TextAlignment $alignment = TextAlignment::LEFT;

    public function __construct(
        string $name,
        Windowable $window,
        protected string $text,
    ) {
        parent::__construct($name, $window);
    }

    public function text(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;
        $this->applyText($text);

        if ($this->sizing !== \Surface\NativeWindows\Enums\SizeRule::FIXED) {
            $this->relayout();
        }

        return $this;
    }

    /**
     * The third sizing rule: fix the width, flow the text, take the height
     * the engine measures for that flow. Re-resolves like any rule — text
     * and font changes re-measure, a centred wrapped label re-centres.
     */
    public function wrap(int $width): static
    {
        $this->sizing = \Surface\NativeWindows\Enums\SizeRule::WRAP;
        $this->width = $width;
        $this->applyWrap($width);

        return $this->relayout();
    }

    public function relayout(): static
    {
        if ($this->sizing === \Surface\NativeWindows\Enums\SizeRule::WRAP) {
            $this->height = $this->measureWrappedHeight($this->width);
        }

        return parent::relayout();
    }

    public function align(TextAlignment $alignment): static
    {
        $this->alignment = $alignment;
        $this->applyAlignment($alignment);

        return $this;
    }

    abstract protected function applyText(string $text): void;

    abstract protected function applyAlignment(TextAlignment $alignment): void;

    /**
     * Turn word-wrapping on in the native node at a fixed width.
     * @return void
     */
    abstract protected function applyWrap(int $width): void;

    /**
     * The height of the current text flowed at $width, in pixels.
     * @return int
     */
    abstract protected function measureWrappedHeight(int $width): int;
}
