<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Surface\NativeWindows\Views\Frame;
use Surface\NativeWindows\Views\ParentView;
use Surface\NativeWindows\Views\Point;
use Surface\NativeWindows\Views\Scroll;
use Surface\NativeWindows\Views\Size;

final class FakeScroll extends Scroll
{
    use FakeParentCalls, FakeConjures;

    public Point $currentOffset;

    public function __construct(
        public readonly CallLog $log,
        int $pointer,
        string $nickname,
        ?ParentView $parent,
        Frame $frame,
        int $content,
        Size $contentSize,
    ) {
        parent::__construct($pointer, $nickname, $parent, $frame, $content, $contentSize);
        $this->currentOffset = new Point(0, 0);
    }

    protected function nativeSetContentSize(Size $size): void
    {
        $this->log->record('setContentSize', $this->pointer(), $size->width, $size->height);
    }

    protected function nativeScrollTo(Point $point): void
    {
        $this->currentOffset = $point;
        $this->log->record('scrollTo', $this->pointer(), $point->x, $point->y);
    }

    protected function nativeOffset(): Point
    {
        $this->log->record('offset', $this->pointer());

        return $this->currentOffset;
    }
}
