<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Closure;
use Surface\NativeWindows\Enums\Orientation;
use Surface\NativeWindows\Views\Frame;
use Surface\NativeWindows\Views\ParentView;
use Surface\NativeWindows\Views\Split;

final class FakeSplit extends Split
{
    use FakeParentCalls;

    public int $divider = 0;

    public function __construct(
        public readonly CallLog $log,
        int $pointer,
        string $nickname,
        ?ParentView $parent,
        Frame $frame,
        Orientation $orientation,
    ) {
        parent::__construct($pointer, $nickname, $parent, $frame, $orientation);
    }

    protected function nativeSetDivider(int $position): void
    {
        $this->divider = $position;
        $this->log->record('setDivider', $this->pointer(), $position);
    }

    protected function nativeGetDivider(): int
    {
        $this->log->record('getDivider', $this->pointer());

        return $this->divider;
    }

    protected function nativeOnDrag(?Closure $trampoline): void
    {
        $this->log->record('onDrag', $this->pointer(), $trampoline);
    }
}
