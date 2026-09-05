<?php

namespace Surface\NativeWindows\Components;

use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\Contracts\NativeWindows\Views\OSScrollView;

/**
 * A scrollable list of sketch-filled slots — Card's body() repeated.
 * LIST only; no grid.
 *
 * Parts: `scroll`, and `item.<key>` per slot.
 */
class DataView extends Component
{
    protected const PAD = 8;

    protected const GAP = 8;

    /** @var list<array{key: string, height: int}> */
    protected array $items = [];

    protected function build(): void
    {
        [$width, $height] = $this->innerSize();
        $this->register('scroll', $this->root->scrollView($this->partName('scroll'), 0, 0, $width, $height));
    }

    /**
     * Append a slot. The group lives in the scroll, full inner width
     * minus pad, stacked with gap 8. Returns the group the sketch fills.
     */
    public function addItem(string $key, int $height = 72): OSGroup
    {
        $this->items[] = ['key' => $key, 'height' => $height];
        $group = $this->scroll()->group($this->partName("item.{$key}"), 0, 0, 1, 1);
        $this->register("item.{$key}", $group);
        $this->layout();

        return $group;
    }

    protected function layout(): void
    {
        [$width, $height] = $this->innerSize();
        $scroll = $this->scroll();
        $scroll->place(0, 0, $width, $height);

        $item_width = max(0, $width - 2 * self::PAD);
        $y = self::PAD;

        foreach ($this->items as $item) {
            $this->parts["item.{$item['key']}"]->place(self::PAD, $y, $item_width, $item['height']);
            $y += $item['height'] + self::GAP;
        }

        $extent = $this->items === [] ? $height : $y - self::GAP + self::PAD;
        $scroll->setContentSize($width, max($height, $extent));
    }

    protected function scroll(): OSScrollView
    {
        /** @var OSScrollView */
        return $this->parts['scroll'];
    }
}
