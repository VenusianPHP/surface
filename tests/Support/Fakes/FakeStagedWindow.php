<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Stage\StagedWindow;

/** A staged window with no native: records host writes and the release/destroy order. */
final class FakeStagedWindow extends StagedWindow
{
    /** @var list<string> */
    public array $log = [];

    protected function applyTitle(string $title): void
    {
        $this->log[] = "title:{$title}";
    }

    protected function applyShow(): void
    {
        $this->log[] = 'show';
    }

    protected function destroyNative(): void
    {
        $executor = $this->executor();
        $this->log[] = 'destroyNative:'.($executor instanceof FakeExecutor && $executor->released ? 'after-release' : 'before-release');
    }
}
