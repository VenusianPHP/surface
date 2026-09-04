<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\NativeWindows\Views\Toggle;

/** A Toggle whose engine hooks record instead of touching a toolkit. */
final class FakeToggle extends Toggle
{
    use RecordsViewApplies;

    /** @var list<bool> */
    public array $applied_on = [];

    /** @var list<bool> */
    public array $applied_enabled = [];

    protected function applyOn(bool $on): void
    {
        $this->applied_on[] = $on;
    }

    protected function applyEnabled(bool $enabled): void
    {
        $this->applied_enabled[] = $enabled;
    }

    /** Test door: what the engine's native flip callback does. */
    public function flip(bool $on): void
    {
        $this->fireToggled($on);
    }
}
