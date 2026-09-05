<?php

namespace Surface\NativeWindows\Components;

use Surface\Contracts\NativeWindows\Views\Color;
use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\Contracts\NativeWindows\Views\OSLabel;
use Surface\NativeWindows\Windowable;

/**
 * A compact status pill: one label inside a padded root. Optional
 * severity reuses Message's fill/ink pairs; without it the badge paints
 * a muted grey. The sketch owns the frame — Badge does not hug the root.
 *
 * Parts: `text`.
 */
class Badge extends Component
{
    protected const PAD = 6;

    public function __construct(
        Windowable $window,
        string $name,
        int $x,
        int $y,
        int $width,
        int $height,
        protected string $text,
        protected ?MessageSeverity $severity = null,
        ?OSGroup $in = null,
    ) {
        parent::__construct($window, $name, $x, $y, $width, $height, $in);
    }

    protected function build(): void
    {
        $this->register('text', $this->root->label($this->partName('text'), $this->text, 0, 0, 1, 1));
        $this->paint();
    }

    protected function layout(): void
    {
        [$width, $height] = $this->innerSize();

        $this->parts['text']->place(
            self::PAD,
            self::PAD,
            max(0, $width - 2 * self::PAD),
            max(0, $height - 2 * self::PAD),
        );
    }

    /** Severity fill on the root, matching ink on the label — or the muted defaults. */
    protected function paint(): void
    {
        if (is_null($this->severity)) {
            $this->root->setBackground(Color::hex('#e5e7eb'));
            $this->label()->setTextColor(Color::hex('#374151'));

            return;
        }

        $this->root->setBackground($this->severity->fill());
        $this->label()->setTextColor($this->severity->ink());
    }

    public function text(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;
        $this->label()->setText($text);

        return $this;
    }

    public function severity(): ?MessageSeverity
    {
        return $this->severity;
    }

    public function setSeverity(?MessageSeverity $severity): static
    {
        $this->severity = $severity;
        $this->paint();

        return $this;
    }

    protected function label(): OSLabel
    {
        /** @var OSLabel */
        return $this->parts['text'];
    }
}
