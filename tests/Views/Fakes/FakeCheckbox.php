<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Closure;
use Surface\NativeWindows\Views\ParentView;
use Surface\NativeWindows\Views\Checkbox;
use Surface\NativeWindows\Views\Frame;

final class FakeCheckbox extends Checkbox
{
    use FakeControlCalls;

    public function __construct(
        public readonly CallLog $log,
        int $pointer,
        string $nickname,
        ?ParentView $parent,
        Frame $frame,
        public bool $checked,
    ) {
        parent::__construct($pointer, $nickname, $parent, $frame);
    }

    protected function nativeSetTitle(string $title): void
    {
        $this->log->record('setTitle', $this->pointer(), $title);
    }

    protected function nativeSetChecked(bool $checked): void
    {
        $this->checked = $checked;
        $this->log->record('setChecked', $this->pointer(), $checked);
    }

    protected function nativeIsChecked(): bool
    {
        $this->log->record('isChecked', $this->pointer());

        return $this->checked;
    }

    protected function nativeOnToggle(?Closure $trampoline): void
    {
        $this->log->record('onToggle', $this->pointer(), $trampoline);
    }
}
