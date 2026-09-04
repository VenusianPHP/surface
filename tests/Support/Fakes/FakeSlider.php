<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\NativeWindows\Views\Slider;

/** A Slider whose engine hooks record instead of touching a toolkit. */
final class FakeSlider extends Slider
{
    use RecordsViewApplies;

    /** @var list<float> */
    public array $applied_values = [];

    /** @var list<array{float, float}> */
    public array $applied_ranges = [];

    /** @var list<bool> */
    public array $applied_enabled = [];

    protected function applyValue(float $value): void
    {
        $this->applied_values[] = $value;
    }

    protected function applyRange(float $min, float $max): void
    {
        $this->applied_ranges[] = [$min, $max];
    }

    protected function applyEnabled(bool $enabled): void
    {
        $this->applied_enabled[] = $enabled;
    }

    /** Test door: what the engine's native value-changed callback does. */
    public function drag(float $value): void
    {
        $this->fireChanged($value);
    }
}
