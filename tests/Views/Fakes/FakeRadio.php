<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Closure;
use Surface\NativeWindows\Views\ParentView;
use Surface\NativeWindows\Views\Frame;
use Surface\NativeWindows\Views\Radio;

final class FakeRadio extends Radio
{
    use FakeControlCalls;

    public bool $selected = false;

    public function __construct(
        public readonly CallLog $log,
        int $pointer,
        string $nickname,
        ?ParentView $parent,
        Frame $frame,
        string $group,
    ) {
        parent::__construct($pointer, $nickname, $parent, $frame, $group);
    }

    protected function nativeSetTitle(string $title): void
    {
        $this->log->record('setTitle', $this->pointer(), $title);
    }

    protected function nativeSetSelected(bool $selected): void
    {
        $this->selected = $selected;
        $this->log->record('setSelected', $this->pointer(), $selected);
    }

    protected function nativeIsSelected(): bool
    {
        $this->log->record('isSelected', $this->pointer());

        return $this->selected;
    }

    protected function nativeOnSelect(?Closure $trampoline): void
    {
        $this->log->record('onSelect', $this->pointer(), $trampoline);
    }
}
