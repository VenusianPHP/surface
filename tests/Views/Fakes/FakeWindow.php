<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Surface\NativeWindows\Views\Box;
use Surface\NativeWindows\Views\Frame;
use Surface\NativeWindows\WindowDelegate;

final class FakeWindow extends WindowDelegate
{
    public int $native_width;

    public int $native_height;

    public function __construct(
        public readonly CallLog $log,
        int $width = 640,
        int $height = 480,
        string $name = 'fake',
    ) {
        parent::__construct($name, $log->nextPointer(), $width, $height);
        $this->native_width = $width;
        $this->native_height = $height;
        $this->setContentPointer(2);
    }

    protected function makeRoot(): Box
    {
        return new FakeBox(
            $this->log,
            $this->content_pointer,
            'root',
            null,
            new Frame(0, 0, $this->current_width, $this->current_height),
        );
    }

    protected function nativeContentWidth(): int
    {
        return $this->native_width;
    }

    protected function nativeContentHeight(): int
    {
        return $this->native_height;
    }

    protected function nativeClose(): void
    {
        $this->log->record('closeWindow', $this->pointer);
    }

    /** Test-only: what a driver does when the OS tore the window down itself. */
    public function simulateOsClose(): void
    {
        $this->markClosed();
    }

    public function present(): void
    {
        $this->log->record('present', $this->pointer);
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function ownsMenuBar(): bool
    {
        return false;
    }

    public function menuPollAction(): string
    {
        return '';
    }

    public function menuAddItem(string $menuTitle, string $itemTitle, string $keyEquivalent, string $actionId): static
    {
        return $this;
    }
}
