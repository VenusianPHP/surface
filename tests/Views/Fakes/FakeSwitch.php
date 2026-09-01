<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Closure;
use Surface\NativeWindows\Views\ParentView;
use Surface\NativeWindows\Views\Frame;
use Surface\NativeWindows\Views\SwitchControl;

final class FakeSwitch extends SwitchControl
{
    use FakeControlCalls;

    public function __construct(
        public readonly CallLog $log,
        int $pointer,
        string $nickname,
        ?ParentView $parent,
        Frame $frame,
        public bool $state,
    ) {
        parent::__construct($pointer, $nickname, $parent, $frame);
    }

    protected function nativeSetOn(bool $on): void
    {
        $this->state = $on;
        $this->log->record('setOn', $this->pointer(), $on);
    }

    protected function nativeIsOn(): bool
    {
        $this->log->record('isOn', $this->pointer());

        return $this->state;
    }

    protected function nativeOnToggle(?Closure $trampoline): void
    {
        $this->log->record('onToggle', $this->pointer(), $trampoline);
    }
}
