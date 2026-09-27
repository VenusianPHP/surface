<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use GeneralPurposeIO\Contracts\IntegratedCircuits\RefreshesOnCommand;
use GeneralPurposeIO\Contracts\IntegratedCircuits\RefreshMode;

/** ePaper: whole frames, shown only on refresh(). */
final class FakeEPaperPanel extends FakePanel implements RefreshesOnCommand
{
    /** @var list<RefreshMode> */
    public array $refreshes = [];

    public function refresh(RefreshMode $mode = RefreshMode::FULL): void
    {
        $this->failIfAsked();
        $this->log[] = 'refresh';
        $this->refreshes[] = $mode;
    }
}
