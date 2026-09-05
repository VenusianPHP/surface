<?php

namespace Surface\NativeWindows\Components;

use Closure;
use Surface\Contracts\NativeWindows\Views\OSScrollView;
use Surface\Contracts\NativeWindows\Views\OSToggleButton;
use Surface\Contracts\NativeWindows\WindowableException;

/**
 * A scrollable stack of exclusive options — Sidebar without collapse,
 * icons, or a breakpoint. Selection is sticky; onSelect fires on user
 * presses only.
 *
 * Parts: `scroll`, and `item.<key>` per option.
 */
class ListBox extends Component
{
    protected const PAD = 8;

    protected const GAP = 4;

    protected const ROW_HEIGHT = 36;

    /** @var list<string> */
    protected array $options = [];

    protected ?string $selected = null;

    protected ?Closure $on_select = null;

    protected function build(): void
    {
        [$width, $height] = $this->innerSize();
        $this->register('scroll', $this->root->scrollView($this->partName('scroll'), 0, 0, $width, $height));
    }

    /**
     * Append an option. The row conjures into the scroll region and the
     * extent grows to hold it.
     * @throws WindowableException When the key is already an option.
     */
    public function addOption(string $key, string $label): static
    {
        if (! is_null($this->option($key))) {
            throw new WindowableException("ListBox '{$this->name}' already has an option '{$key}'.");
        }

        $this->options[] = $key;

        $row = $this->scroll()->toggleButton($this->partName("item.{$key}"), $label, false, 0, 0, 1, 1);
        $row->onToggle(function (bool $pressed) use ($key, $row): void {
            if ($pressed) {
                $this->select($key);

                if (! is_null($this->on_select)) {
                    ($this->on_select)($key);
                }

                return;
            }

            if ($this->selected === $key) {
                $row->setPressed(true);
            }
        });
        $this->register("item.{$key}", $row);
        $this->layout();

        return $this;
    }

    /**
     * Make one option the selection — its row presses, every other row
     * releases. Silent: the hook is for user presses.
     * @throws WindowableException When no such option exists.
     */
    public function select(string $key): static
    {
        if (is_null($this->option($key))) {
            throw new WindowableException("ListBox '{$this->name}' has no option '{$key}'.");
        }

        $this->selected = $key;

        foreach ($this->options as $option) {
            $this->row($option)->setPressed($option === $key);
        }

        return $this;
    }

    public function selectedKey(): ?string
    {
        return $this->selected;
    }

    /** Hook invoked when the user picks an option, in-pump. Receives the key. */
    public function onSelect(callable $hook): static
    {
        $this->on_select = $hook(...);

        return $this;
    }

    protected function layout(): void
    {
        [$width, $height] = $this->innerSize();
        $scroll = $this->scroll();
        $scroll->place(0, 0, $width, $height);

        $row_width = max(0, $width - 2 * self::PAD);
        $y = self::PAD;

        foreach ($this->options as $key) {
            $this->row($key)->place(self::PAD, $y, $row_width, self::ROW_HEIGHT);
            $y += self::ROW_HEIGHT + self::GAP;
        }

        $extent = $this->options === [] ? $height : $y - self::GAP + self::PAD;
        $scroll->setContentSize($width, max($height, $extent));
    }

    protected function option(string $key): ?string
    {
        foreach ($this->options as $option) {
            if ($option === $key) {
                return $option;
            }
        }

        return null;
    }

    protected function scroll(): OSScrollView
    {
        /** @var OSScrollView */
        return $this->parts['scroll'];
    }

    protected function row(string $key): OSToggleButton
    {
        /** @var OSToggleButton */
        return $this->parts["item.{$key}"];
    }
}
