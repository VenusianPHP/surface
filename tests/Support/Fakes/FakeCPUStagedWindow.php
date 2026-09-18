<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Stage\CPUStagedWindow;

/** A CPU stage with no native: records every present and the close order. */
final class FakeCPUStagedWindow extends CPUStagedWindow
{
    /** @var list<string> RGBA8 blobs handed to the host */
    public array $presents = [];

    /** @var list<string> */
    public array $log = [];

    /** When set, releaseEngine() logs the call, then throws this. */
    public ?\Throwable $release_failure = null;

    protected function applyPresent(string $rgba8): void
    {
        $this->presents[] = $rgba8;
    }

    protected function applyTitle(string $title): void
    {
        $this->log[] = "title:{$title}";
    }

    protected function applyShow(): void
    {
        $this->log[] = 'show';
    }

    protected function releaseEngine(): void
    {
        $this->log[] = 'releaseEngine';

        if (! is_null($this->release_failure)) {
            throw $this->release_failure;
        }
    }

    protected function destroyNative(): void
    {
        $this->log[] = 'destroyNative';
    }
}
