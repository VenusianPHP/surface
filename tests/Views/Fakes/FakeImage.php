<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Surface\NativeWindows\Enums\ImageFit;
use Surface\NativeWindows\Views\Frame;
use Surface\NativeWindows\Views\Image;
use Surface\NativeWindows\Views\ParentView;
use Surface\NativeWindows\Views\Size;

final class FakeImage extends Image
{
    use FakeParentCalls;

    public bool $acceptSource = true;

    public Size $natural;

    public function __construct(
        public readonly CallLog $log,
        int $pointer,
        string $nickname,
        ?ParentView $parent,
        Frame $frame,
        string $source,
    ) {
        parent::__construct($pointer, $nickname, $parent, $frame, $source);
        $this->natural = new Size($frame->width, $frame->height);
    }

    protected function nativeSetSource(string $path): bool
    {
        $this->log->record('setSource', $this->pointer(), $path);

        return $this->acceptSource;
    }

    protected function nativeSetFit(ImageFit $fit): void
    {
        $this->log->record('setFit', $this->pointer(), $fit);
    }

    protected function nativeMeasure(): Size
    {
        $this->log->record('measure', $this->pointer());

        return $this->natural;
    }
}
