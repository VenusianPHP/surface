<?php

namespace Surface\NativeWindows\Components;

use Closure;
use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\Contracts\NativeWindows\Views\OSLabel;
use Surface\NativeWindows\Windowable;

/**
 * An inline callout: severity-coloured fill, matching ink, and — when
 * closable — a dismiss button that removes the whole component.
 *
 * Parts: `text`, and `close` when closable. The onClose hook runs after
 * the subtree is gone, in-pump like every view hook.
 */
class Message extends Component
{
    protected const PAD = 10;

    protected const CLOSE_SIZE = 22;

    protected ?Closure $on_close = null;

    public function __construct(
        Windowable $window,
        string $name,
        int $x,
        int $y,
        int $width,
        int $height,
        protected string $text,
        protected MessageSeverity $severity = MessageSeverity::INFO,
        protected bool $closable = false,
        ?OSGroup $in = null,
    ) {
        parent::__construct($window, $name, $x, $y, $width, $height, $in);
    }

    protected function build(): void
    {
        $text = $this->root->label($this->partName('text'), $this->text, 0, 0, 1, 1);
        $this->register('text', $text);

        if ($this->closable) {
            $close = $this->root->button($this->partName('close'), '×', 0, 0, 1, 1);
            $close->onClick(function (): void {
                $this->remove();

                if (! is_null($this->on_close)) {
                    ($this->on_close)();
                }
            });
            $this->register('close', $close);
        }

        $this->paint();
    }

    protected function layout(): void
    {
        [$width, $height] = $this->innerSize();
        $text_width = $width - 2 * self::PAD - ($this->closable ? self::CLOSE_SIZE + self::PAD : 0);

        $this->parts['text']->place(
            self::PAD,
            max(0, (int) floor(($height - 18) / 2)),
            max(0, $text_width),
            18,
        );

        if (isset($this->parts['close'])) {
            $this->parts['close']->place(
                max(0, $width - self::PAD - self::CLOSE_SIZE),
                max(0, (int) floor(($height - self::CLOSE_SIZE) / 2)),
                self::CLOSE_SIZE,
                self::CLOSE_SIZE,
            );
        }
    }

    /** The severity's fill on the root, its ink on the text. */
    protected function paint(): void
    {
        $this->root->setBackground($this->severity->fill());
        /** @var OSLabel $text */
        $text = $this->parts['text'];
        $text->setTextColor($this->severity->ink());
    }

    public function text(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;
        /** @var OSLabel $label */
        $label = $this->parts['text'];
        $label->setText($text);

        return $this;
    }

    public function severity(): MessageSeverity
    {
        return $this->severity;
    }

    public function setSeverity(MessageSeverity $severity): static
    {
        $this->severity = $severity;
        $this->paint();

        return $this;
    }

    /** Hook invoked after the dismiss button has removed the component. */
    public function onClose(callable $hook): static
    {
        $this->on_close = $hook(...);

        return $this;
    }
}
