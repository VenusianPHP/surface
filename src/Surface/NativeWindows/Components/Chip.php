<?php

namespace Surface\NativeWindows\Components;

use Closure;
use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\NativeWindows\Windowable;

/**
 * A compact labelled chip: Message without severity. When removable, a
 * dismiss button removes the whole component then hooks onRemove.
 *
 * Parts: `label`, and `close` when removable.
 */
class Chip extends Component
{
    protected const PAD = 8;

    protected const CLOSE_SIZE = 22;

    protected ?Closure $on_remove = null;

    public function __construct(
        Windowable $window,
        string $name,
        int $x,
        int $y,
        int $width,
        int $height,
        protected string $label,
        protected bool $removable = false,
        ?OSGroup $in = null,
    ) {
        parent::__construct($window, $name, $x, $y, $width, $height, $in);
    }

    protected function build(): void
    {
        $this->register('label', $this->root->label($this->partName('label'), $this->label, 0, 0, 1, 1));

        if ($this->removable) {
            $close = $this->root->button($this->partName('close'), '×', 0, 0, 1, 1);
            $close->onClick(function (): void {
                $this->remove();

                if (! is_null($this->on_remove)) {
                    ($this->on_remove)();
                }
            });
            $this->register('close', $close);
        }
    }

    protected function layout(): void
    {
        [$width, $height] = $this->innerSize();
        $text_width = $width - 2 * self::PAD - ($this->removable ? self::CLOSE_SIZE + self::PAD : 0);

        $this->parts['label']->place(
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

    /** Hook invoked after the dismiss button has removed the component. */
    public function onRemove(callable $hook): static
    {
        $this->on_remove = $hook(...);

        return $this;
    }
}
