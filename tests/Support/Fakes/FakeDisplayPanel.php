<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use GeneralPurposeIO\Contracts\IntegratedCircuits\Switchable;
use GeneralPurposeIO\Contracts\IntegratedCircuits\WindowAddressable;

/** An OLED or TFT: takes region writes and can be switched off. */
final class FakeDisplayPanel extends FakePanel implements Switchable, WindowAddressable
{
    /** @var list<bool> */
    public array $switches = [];

    public function setDisplay(bool $on): void
    {
        $this->failIfAsked();
        $this->log[] = $on ? 'on' : 'off';
        $this->switches[] = $on;
    }
}
