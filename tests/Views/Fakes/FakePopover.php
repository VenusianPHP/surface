<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Closure;
use Surface\NativeWindows\Views\Frame;
use Surface\NativeWindows\Views\ParentView;
use Surface\NativeWindows\Views\Popover;
use Surface\NativeWindows\Views\Size;

final class FakePopover extends Popover
{
    use FakeParentCalls, FakeConjures;

    public bool $shown = false;

    public bool $canShow = true;

    public ?Closure $close = null;

    public function __construct(
        public readonly CallLog $log,
        int $pointer,
        string $nickname,
        ?ParentView $parent,
        Frame $frame,
        int $content,
    ) {
        parent::__construct($pointer, $nickname, $parent, $frame, $content);
    }

    protected function nativeShow(int $anchorPointer): bool
    {
        $this->log->record('show', $this->pointer(), $anchorPointer);
        $this->shown = $this->canShow;

        return $this->canShow;
    }

    protected function nativeHide(): void
    {
        $this->shown = false;
        $this->log->record('hide', $this->pointer());
        $this->close?->__invoke();
    }

    protected function nativeIsShown(): bool
    {
        $this->log->record('isShown', $this->pointer());

        return $this->shown;
    }

    protected function nativeOnClose(?Closure $trampoline): void
    {
        $this->close = $trampoline;
        $this->log->record('onClose', $this->pointer(), $trampoline);
    }

    protected function nativeSetContentSize(Size $size): void
    {
        $this->log->record('setContentSize', $this->pointer(), $size->width, $size->height);
    }
}
