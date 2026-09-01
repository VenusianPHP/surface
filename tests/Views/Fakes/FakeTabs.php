<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Closure;
use Surface\NativeWindows\Views\Frame;
use Surface\NativeWindows\Views\Pane;
use Surface\NativeWindows\Views\ParentView;
use Surface\NativeWindows\Views\Tabs;

final class FakeTabs extends Tabs
{
    use FakeParentCalls;

    public int $selectedIndex = -1;

    public function __construct(
        public readonly CallLog $log,
        int $pointer,
        string $nickname,
        ?ParentView $parent,
        Frame $frame,
    ) {
        parent::__construct($pointer, $nickname, $parent, $frame);
    }

    protected function nativeCreatePage(string $nickname, string $title): Pane
    {
        $pointer = $this->log->nextPointer();
        $this->log->record('createPage', $pointer, $nickname, $title);

        if ($this->selectedIndex < 0) {
            $this->selectedIndex = 0;
        }

        return new FakePane($this->log, $pointer, $nickname, $this, new Frame(0, 0, 0, 0));
    }

    protected function nativeRemovePage(Pane $page): void
    {
        $this->log->record('removePage', $this->pointer(), $page->pointer());

        $remaining = max(0, $this->count() - 1);

        if ($remaining === 0) {
            $this->selectedIndex = -1;
        } elseif ($this->selectedIndex >= $remaining) {
            $this->selectedIndex = $remaining - 1;
        }
    }

    protected function nativeSetSelected(int $index): void
    {
        $this->selectedIndex = $index;
        $this->log->record('setSelected', $this->pointer(), $index);
    }

    protected function nativeGetSelected(): int
    {
        $this->log->record('getSelected', $this->pointer());

        return $this->selectedIndex;
    }

    protected function nativeOnChange(?Closure $trampoline): void
    {
        $this->log->record('onChange', $this->pointer(), $trampoline);
    }
}
