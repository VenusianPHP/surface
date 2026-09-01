<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Closure;
use Surface\NativeWindows\Views\ParentView;
use Surface\NativeWindows\Views\Dropdown;
use Surface\NativeWindows\Views\Frame;

final class FakeDropdown extends Dropdown
{
    use FakeControlCalls;

    public function __construct(
        public readonly CallLog $log,
        int $pointer,
        string $nickname,
        ?ParentView $parent,
        Frame $frame,
        array $items,
        public int $current,
    ) {
        parent::__construct($pointer, $nickname, $parent, $frame, $items);
    }

    protected function nativeSetItems(array $items): void
    {
        $this->log->record('setItems', $this->pointer(), $items);
    }

    protected function nativeSetSelected(int $index): void
    {
        $this->current = $index;
        $this->log->record('setSelected', $this->pointer(), $index);
    }

    protected function nativeGetSelected(): int
    {
        $this->log->record('getSelected', $this->pointer());

        return $this->current;
    }

    protected function nativeOnChange(?Closure $trampoline): void
    {
        $this->log->record('onChange', $this->pointer(), $trampoline);
    }
}
