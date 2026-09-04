<?php

namespace Surface\NativeWindows\Components;

use Closure;
use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\Contracts\NativeWindows\Views\OSScrollView;
use Surface\Contracts\NativeWindows\Views\OSToggleButton;
use Surface\Contracts\NativeWindows\WindowableException;
use Surface\NativeWindows\Windowable;

/**
 * A scrollable stack of navigation links, one selected at a time.
 *
 * Each link is a full-width ToggleButton row — the native pressed state
 * IS the selected marker, identical on both engines, and rows past the
 * viewport scroll because they live in a ScrollView. Icons are glyph
 * strings (emoji or symbol); when the sidebar is placed narrower than its
 * collapse breakpoint the rows relabel to just the glyph (or the label's
 * first letter) — the snap lives entirely in layout().
 *
 * Selection is sticky: pressing the selected row again re-presses it, and
 * exactly one row is pressed once anything has been selected. The
 * onSelect hook runs in-pump with the picked key, on user presses only —
 * programmatic select() stays silent.
 *
 * Parts: `scroll`, and `link.<key>` per link.
 */
class Sidebar extends Component
{
    protected const PAD = 8;

    protected const GAP = 4;

    protected const ROW_HEIGHT = 36;

    /** @var list<array{key: string, label: string, icon: ?string}> */
    protected array $links = [];

    protected ?string $selected = null;

    protected ?Closure $on_select = null;

    public function __construct(
        Windowable $window,
        string $name,
        int $x,
        int $y,
        int $width,
        int $height,
        protected int $collapse_below = 140,
        ?OSGroup $in = null,
    ) {
        parent::__construct($window, $name, $x, $y, $width, $height, $in);
    }

    protected function build(): void
    {
        [$width, $height] = $this->innerSize();
        $this->register('scroll', $this->root->scrollView($this->partName('scroll'), 0, 0, $width, $height));
    }

    /**
     * Append a link. The row conjures into the scroll region and the
     * extent grows to hold it.
     * @throws WindowableException When the key is already a link.
     */
    public function addLink(string $key, string $label, ?string $icon = null): static
    {
        if (! is_null($this->link($key))) {
            throw new WindowableException("Sidebar '{$this->name}' already has a link '{$key}'.");
        }

        $this->links[] = ['key' => $key, 'label' => $label, 'icon' => $icon];

        $row = $this->scroll()->toggleButton($this->partName("link.{$key}"), $this->rowLabel(count($this->links) - 1), false, 0, 0, 1, 1);
        $row->onToggle(function (bool $pressed) use ($key, $row): void {
            if ($pressed) {
                $this->select($key);

                if (! is_null($this->on_select)) {
                    ($this->on_select)($key);
                }

                return;
            }

            // Unpressing the selected row is not a deselection — sticky.
            if ($this->selected === $key) {
                $row->setPressed(true);
            }
        });
        $this->register("link.{$key}", $row);

        $this->layout();

        return $this;
    }

    /**
     * Make one link the selection — its row presses, every other row
     * releases. Silent: the hook is for user presses.
     * @throws WindowableException When no such link exists.
     */
    public function select(string $key): static
    {
        if (is_null($this->link($key))) {
            throw new WindowableException("Sidebar '{$this->name}' has no link '{$key}'.");
        }

        $this->selected = $key;

        foreach ($this->links as $link) {
            $this->row($link['key'])->setPressed($link['key'] === $key);
        }

        return $this;
    }

    public function selectedKey(): ?string
    {
        return $this->selected;
    }

    /** Hook invoked when the user picks a link, in-pump. Receives the key. */
    public function onSelect(callable $hook): static
    {
        $this->on_select = $hook(...);

        return $this;
    }

    /** @return list<string> The link keys, in order. */
    public function links(): array
    {
        return array_map(fn (array $link): string => $link['key'], $this->links);
    }

    /** Whether the current frame is under the collapse breakpoint. */
    public function collapsed(): bool
    {
        [$width] = $this->innerSize();

        return $width < $this->collapse_below;
    }

    protected function layout(): void
    {
        [$width, $height] = $this->innerSize();
        $scroll = $this->scroll();
        $scroll->place(0, 0, $width, $height);

        $row_width = max(0, $width - 2 * self::PAD);
        $y = self::PAD;

        foreach ($this->links as $index => $link) {
            $row = $this->row($link['key']);
            $row->setLabel($this->rowLabel($index));
            $row->place(self::PAD, $y, $row_width, self::ROW_HEIGHT);
            $y += self::ROW_HEIGHT + self::GAP;
        }

        // The extent holds every row; under one viewport it just matches.
        $extent = $this->links === [] ? $height : $y - self::GAP + self::PAD;
        $scroll->setContentSize($width, max($height, $extent));
    }

    /** What a row reads at the current width: full, or snapped to its glyph. */
    protected function rowLabel(int $index): string
    {
        $link = $this->links[$index];

        if (! $this->collapsed()) {
            return is_null($link['icon']) ? $link['label'] : "{$link['icon']}  {$link['label']}";
        }

        return $link['icon'] ?? mb_substr($link['label'], 0, 1);
    }

    /** @return array{key: string, label: string, icon: ?string}|null */
    protected function link(string $key): ?array
    {
        foreach ($this->links as $link) {
            if ($link['key'] === $key) {
                return $link;
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
        return $this->parts["link.{$key}"];
    }
}
