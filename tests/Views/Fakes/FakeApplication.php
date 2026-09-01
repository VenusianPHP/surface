<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Surface\Contracts\NativeWindows\WindowDelegate;
use Surface\NativeWindows\WhatTheOSConsidersAnApplication;
use Voyager\NutsAndBolts\Collection;

final class FakeApplication extends WhatTheOSConsidersAnApplication
{
    public function __construct(public readonly CallLog $log)
    {
        $this->opened_windows = new Collection;
    }

    public function createWindow(string $name, int $width, int $height, ?WindowDelegate &$window = null): static
    {
        $window = new FakeWindow($this->log, $width, $height, $name);
        $this->log->record('createWindow', $window->pointer, $name, $width, $height);
        $this->opened_windows->offsetSet($name, $window);

        return $this;
    }

    public function pump(): void {}

    public function terminate(): void {}

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

    protected function installAboutItem(): void
    {
        $this->log->record('aboutItem', 0, $this->aboutTitle());
    }
}
