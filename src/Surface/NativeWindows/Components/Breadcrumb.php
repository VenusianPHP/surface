<?php

namespace Surface\NativeWindows\Components;

use Closure;
use Surface\Contracts\NativeWindows\Views\OSButton;
use Surface\Contracts\NativeWindows\WindowableException;

/**
 * A Toolbar-style trail of buttons with auto `›` separators. Every item
 * is a Button from the start — the current last is disabled and its
 * onClick is a no-op, so appending does not have to remove/reconjure.
 * onSelect fires with the key for enabled (non-last) clicks only.
 *
 * Parts: `item.<key>`, `sep.<n>`.
 */
class Breadcrumb extends Component
{
    protected const PAD = 8;

    protected const GAP = 8;

    protected const SEP_WIDTH = 12;

    protected const SEP_HEIGHT = 18;

    /** @var list<string> */
    protected array $items = [];

    protected int $separators = 0;

    protected ?Closure $on_select = null;

    protected function build(): void
    {
        // Items flow in through addItem().
    }

    /**
     * Append a crumb. The previous last becomes enabled; this one is the
     * new last (disabled, click is a no-op).
     * @throws WindowableException When the key is already an item.
     */
    public function addItem(string $key, string $label): static
    {
        if (isset($this->parts["item.{$key}"])) {
            throw new WindowableException("Breadcrumb '{$this->name}' already has an item '{$key}'.");
        }

        if ($this->items !== []) {
            $this->item(end($this->items))->setEnabled(true);
            $name = 'sep.'.(++$this->separators);
            $this->register($name, $this->root->label($this->partName($name), '›', 0, 0, self::SEP_WIDTH, self::SEP_HEIGHT));
        }

        $button = $this->root->button($this->partName("item.{$key}"), $label, 0, 0, 1, 1);
        $button->onClick(function () use ($key): void {
            if ($this->lastKey() === $key) {
                return;
            }

            if (! is_null($this->on_select)) {
                ($this->on_select)($key);
            }
        });
        $button->setEnabled(false);
        $this->register("item.{$key}", $button);
        $this->items[] = $key;
        $this->layout();

        return $this;
    }

    /** Hook invoked when the user clicks an enabled (non-last) crumb. */
    public function onSelect(callable $hook): static
    {
        $this->on_select = $hook(...);

        return $this;
    }

    protected function layout(): void
    {
        [, $height] = $this->innerSize();
        $x = self::PAD;

        foreach ($this->items as $index => $key) {
            if ($index > 0) {
                $sep = $this->parts['sep.'.$index];
                $sep->place(
                    $x,
                    max(0, (int) floor(($height - self::SEP_HEIGHT) / 2)),
                    self::SEP_WIDTH,
                    self::SEP_HEIGHT,
                );
                $x += self::SEP_WIDTH + self::GAP;
            }

            $view = $this->item($key);
            $view->hug();
            $frame = $view->frame();
            $view->place($x, max(0, (int) floor(($height - $frame['height']) / 2)), $frame['width'], $frame['height']);
            $x += $frame['width'] + self::GAP;
        }
    }

    protected function lastKey(): ?string
    {
        if ($this->items === []) {
            return null;
        }

        return $this->items[array_key_last($this->items)];
    }

    protected function item(string $key): OSButton
    {
        /** @var OSButton */
        return $this->parts["item.{$key}"];
    }
}
