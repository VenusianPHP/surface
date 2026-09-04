<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\NativeWindows\Views\ToggleButton;

/** A ToggleButton whose engine hooks record instead of touching a toolkit. */
final class FakeToggleButton extends ToggleButton
{
    use RecordsTextStyle;
    use RecordsViewApplies;

    /** @var list<string> */
    public array $applied_labels = [];

    /** @var list<bool> */
    public array $applied_pressed = [];

    /** @var list<bool> */
    public array $applied_enabled = [];

    protected function applyLabel(string $label): void
    {
        $this->applied_labels[] = $label;
    }

    protected function applyPressed(bool $pressed): void
    {
        $this->applied_pressed[] = $pressed;
    }

    protected function applyEnabled(bool $enabled): void
    {
        $this->applied_enabled[] = $enabled;
    }

    /** Test door: what the engine's native toggle callback does. */
    public function press(bool $pressed): void
    {
        $this->fireToggled($pressed);
    }
}
