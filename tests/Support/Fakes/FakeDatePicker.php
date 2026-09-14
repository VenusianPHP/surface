<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\NativeWindows\Views\DatePicker;

/** A DatePicker whose engine hooks record instead of touching a toolkit. */
final class FakeDatePicker extends DatePicker
{
    use RecordsViewApplies;

    /** @var list<array{int, int, int}> */
    public array $applied_dates = [];

    /** @var list<bool> */
    public array $applied_enabled = [];

    protected function applyDate(int $year, int $month, int $day): void
    {
        $this->applied_dates[] = [$year, $month, $day];
    }

    protected function applyEnabled(bool $enabled): void
    {
        $this->applied_enabled[] = $enabled;
    }

    /** Test door: what the engine's native day-selected callback does. */
    public function pickDate(int $year, int $month, int $day): void
    {
        $this->fireChanged($year, $month, $day);
    }
}
