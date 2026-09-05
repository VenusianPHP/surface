<?php

namespace Surface\NativeWindows\Components;

use Closure;
use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\Contracts\NativeWindows\Views\OSToggleButton;
use Surface\Contracts\NativeWindows\WindowableException;

/**
 * A header strip of exclusive ToggleButtons over stacked panel Groups.
 * Visibility is how panels swap: only the selected panel is shown.
 * The first tab selects itself silently. Programmatic select() stays
 * silent; onSelect fires on user presses only. Selection is sticky.
 *
 * Parts: `tab.<key>`, `panel.<key>`.
 */
class Tabs extends Component
{
    protected const PAD = 8;

    protected const GAP = 4;

    protected const HEADER_HEIGHT = 32;

    /** @var list<string> */
    protected array $tabs = [];

    protected ?string $selected = null;

    protected ?Closure $on_select = null;

    protected function build(): void
    {
        // Tabs flow in through addTab().
    }

    /**
     * Append a tab. Returns the panel Group the sketch fills.
     * The first tab selects itself silently.
     * @throws WindowableException When the key is already a tab.
     */
    public function addTab(string $key, string $label): OSGroup
    {
        if (! is_null($this->tab($key))) {
            throw new WindowableException("Tabs '{$this->name}' already has a tab '{$key}'.");
        }

        $this->tabs[] = $key;

        $header = $this->root->toggleButton($this->partName("tab.{$key}"), $label, false, 0, 0, 1, 1);
        $header->onToggle(function (bool $pressed) use ($key, $header): void {
            if ($pressed) {
                $this->select($key);

                if (! is_null($this->on_select)) {
                    ($this->on_select)($key);
                }

                return;
            }

            if ($this->selected === $key) {
                $header->setPressed(true);
            }
        });
        $this->register("tab.{$key}", $header);

        $panel = $this->root->group($this->partName("panel.{$key}"), 0, 0, 1, 1);
        $this->register("panel.{$key}", $panel);

        if (is_null($this->selected)) {
            $this->select($key);
        }

        $this->layout();

        return $panel;
    }

    /**
     * Make one tab the selection — its header presses, every other
     * releases, only its panel is shown. Silent: the hook is for user presses.
     * @throws WindowableException When no such tab exists.
     */
    public function select(string $key): static
    {
        if (is_null($this->tab($key))) {
            throw new WindowableException("Tabs '{$this->name}' has no tab '{$key}'.");
        }

        $this->selected = $key;

        foreach ($this->tabs as $tab) {
            $this->header($tab)->setPressed($tab === $key);
            $panel = $this->panel($tab);
            if ($tab === $key) {
                $panel->show();
            } else {
                $panel->hide();
            }
        }

        return $this;
    }

    public function selectedKey(): ?string
    {
        return $this->selected;
    }

    /** Hook invoked when the user picks a tab, in-pump. Receives the key. */
    public function onSelect(callable $hook): static
    {
        $this->on_select = $hook(...);

        return $this;
    }

    protected function layout(): void
    {
        [$width, $height] = $this->innerSize();
        $x = self::PAD;

        foreach ($this->tabs as $key) {
            $header = $this->header($key);
            $header->hug();
            $frame = $header->frame();
            $header->place(
                $x,
                max(0, (int) floor((self::HEADER_HEIGHT - $frame['height']) / 2)),
                $frame['width'],
                $frame['height'],
            );
            $x += $frame['width'] + self::GAP;
        }

        $panel_height = max(0, $height - self::HEADER_HEIGHT);

        foreach ($this->tabs as $key) {
            $panel = $this->panel($key);
            $panel->place(0, self::HEADER_HEIGHT, $width, $panel_height);
            if ($key === $this->selected) {
                $panel->show();
            } else {
                $panel->hide();
            }
        }
    }

    protected function tab(string $key): ?string
    {
        foreach ($this->tabs as $tab) {
            if ($tab === $key) {
                return $tab;
            }
        }

        return null;
    }

    protected function header(string $key): OSToggleButton
    {
        /** @var OSToggleButton */
        return $this->parts["tab.{$key}"];
    }

    protected function panel(string $key): OSGroup
    {
        /** @var OSGroup */
        return $this->parts["panel.{$key}"];
    }
}
