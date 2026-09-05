<?php

namespace Surface\NativeWindows\Components;

use Closure;
use Surface\Contracts\NativeWindows\Views\OSToggleButton;
use Surface\Contracts\NativeWindows\WindowableException;

/**
 * A horizontal exclusive-choice strip. Each option is a ToggleButton
 * flowed like Toolbar (natural width, vertically centred); selection is
 * Sidebar's sticky rule. Programmatic select() stays silent.
 *
 * Parts: `option.<key>`.
 */
class SelectButton extends Component
{
    protected const PAD = 4;

    protected const GAP = 4;

    /** @var list<string> */
    protected array $options = [];

    protected ?string $selected = null;

    protected ?Closure $on_select = null;

    protected function build(): void
    {
        // Options flow in through addOption().
    }

    /**
     * Append an option. The toggle hugs its label and the strip re-flows.
     * @throws WindowableException When the key is already an option.
     */
    public function addOption(string $key, string $label): static
    {
        if (! is_null($this->option($key))) {
            throw new WindowableException("SelectButton '{$this->name}' already has an option '{$key}'.");
        }

        $this->options[] = $key;

        $toggle = $this->root->toggleButton($this->partName("option.{$key}"), $label, false, 0, 0, 1, 1);
        $toggle->onToggle(function (bool $pressed) use ($key, $toggle): void {
            if ($pressed) {
                $this->select($key);

                if (! is_null($this->on_select)) {
                    ($this->on_select)($key);
                }

                return;
            }

            if ($this->selected === $key) {
                $toggle->setPressed(true);
            }
        });
        $this->register("option.{$key}", $toggle);
        $this->layout();

        return $this;
    }

    /**
     * Make one option the selection — its toggle presses, every other
     * releases. Silent: the hook is for user presses.
     * @throws WindowableException When no such option exists.
     */
    public function select(string $key): static
    {
        if (is_null($this->option($key))) {
            throw new WindowableException("SelectButton '{$this->name}' has no option '{$key}'.");
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
        [, $height] = $this->innerSize();
        $x = self::PAD;

        foreach ($this->options as $key) {
            $view = $this->row($key);
            $view->hug();
            $frame = $view->frame();
            $view->place($x, max(0, (int) floor(($height - $frame['height']) / 2)), $frame['width'], $frame['height']);
            $x += $frame['width'] + self::GAP;
        }
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

    protected function row(string $key): OSToggleButton
    {
        /** @var OSToggleButton */
        return $this->parts["option.{$key}"];
    }
}
