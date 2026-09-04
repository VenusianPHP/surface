<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\NativeWindows\Views\TextInput;

/** A TextInput whose engine hooks record instead of touching a toolkit. */
final class FakeTextInput extends TextInput
{
    use RecordsTextStyle;
    use RecordsViewApplies;

    /** @var list<string> */
    public array $applied_values = [];

    /** @var list<string> */
    public array $applied_placeholders = [];

    /** @var list<bool> */
    public array $applied_enabled = [];

    protected function applyValue(string $value): void
    {
        $this->applied_values[] = $value;
    }

    protected function applyPlaceholder(string $placeholder): void
    {
        $this->applied_placeholders[] = $placeholder;
    }

    protected function applyEnabled(bool $enabled): void
    {
        $this->applied_enabled[] = $enabled;
    }

    /** Test door: what the engine's native edit callback does. */
    public function typeText(string $value): void
    {
        $this->fireChanged($value);
    }

    /** Test door: what the engine's Enter callback does. */
    public function submit(): void
    {
        $this->fireSubmitted();
    }
}
