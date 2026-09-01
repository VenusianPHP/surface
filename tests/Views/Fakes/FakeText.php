<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Closure;
use Surface\NativeWindows\Views\Color;
use Surface\NativeWindows\Views\Frame;
use Surface\NativeWindows\Views\ParentView;
use Surface\NativeWindows\Views\Text;

final class FakeText extends Text
{
    use FakeControlCalls;

    public ?Closure $change = null;

    public function __construct(
        public readonly CallLog $log,
        int $pointer,
        string $nickname,
        ?ParentView $parent,
        Frame $frame,
        public string $current,
        bool $editable = true,
    ) {
        parent::__construct($pointer, $nickname, $parent, $frame, $editable);
    }

    protected function nativeGetValue(): string
    {
        $this->log->record('getValue', $this->pointer());

        return $this->current;
    }

    protected function nativeSetValue(string $value): void
    {
        $this->current = $value;
        $this->log->record('setValue', $this->pointer(), $value);
    }

    protected function nativeSetEditable(bool $editable): void
    {
        $this->log->record('setEditable', $this->pointer(), $editable);
    }

    protected function nativeSetTextColor(Color $color): void
    {
        $this->log->record('setTextColor', $this->pointer(), $color->red, $color->green, $color->blue, $color->alpha);
    }

    protected function nativeOnChange(?Closure $trampoline): void
    {
        $this->change = $trampoline;
        $this->log->record('onChange', $this->pointer(), $trampoline);
    }
}
