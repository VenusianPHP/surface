<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Closure;
use Surface\NativeWindows\Views\ParentView;
use Surface\NativeWindows\Views\Button;
use Surface\NativeWindows\Views\Frame;

final class FakeButton extends Button
{
    use FakeControlCalls;

    public function __construct(
        public readonly CallLog $log,
        int $pointer,
        string $nickname,
        ?ParentView $parent,
        Frame $frame,
    ) {
        parent::__construct($pointer, $nickname, $parent, $frame);
    }

    protected function nativeSetTitle(string $title): void
    {
        $this->log->record('setTitle', $this->pointer(), $title);
    }

    protected function nativeOnClick(?Closure $trampoline): void
    {
        $this->log->record('onClick', $this->pointer(), $trampoline);
    }
}
