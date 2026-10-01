<?php

declare(strict_types=1);

namespace Venusian\Surface\Tests\Fixtures;

use Surface\Bridge\BridgedToolkitSession;

/** A session with no toolkit behind it: records what the base class asks of the engine. */
final class FakeSession extends BridgedToolkitSession
{
    /** @var list<string> */
    public array $calls = [];

    /** @var list<int> */
    public array $pumped = [];

    public ?int $woken_by = null;

    protected function initializeEngine(): void { $this->calls[] = 'initialize'; }

    protected function connectToEngine(): void { $this->calls[] = 'connect'; }

    protected function disconnectEngine(): void { $this->calls[] = 'disconnect'; }

    public function pump(int $budget_ns): int
    {
        $this->pumped[] = $budget_ns;

        return 0;
    }

    protected function wakeDescriptor(int $fd): void { $this->woken_by = $fd; }

    protected function releaseWakeDescriptor(): void { $this->woken_by = null; }

    /** @return list<object> */
    public function outbox(): array { return $this->outbox; }
}
