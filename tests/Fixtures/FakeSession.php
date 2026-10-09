<?php

declare(strict_types=1);

namespace Venusian\Surface\Tests\Fixtures;

use Closure;
use Surface\Bridge\BridgedToolkitSession;

/** A session with no toolkit behind it: records what the base class asks of the engine. */
final class FakeSession extends BridgedToolkitSession
{
    /** @var list<string> */
    public array $calls = [];

    /** @var list<int> */
    public array $pumped = [];

    public ?int $woken_by = null;

    public bool $sleeps_natively = true;

    public int $released = 0;

    /** Run on every pump with the session, the way a toolkit dispatches what arrived. */
    public ?Closure $on_pump = null;

    /** Whether this session is under the one-toolkit rule, as on macOS. Off, so sessions the suite leaves connected never count. */
    public bool $mac = false;

    protected function onMacOs(): bool { return $this->mac; }

    protected function sleepsNatively(): bool { return $this->sleeps_natively; }

    protected function initializeEngine(): void { $this->calls[] = 'initialize'; }

    protected function connectToEngine(): void { $this->calls[] = 'connect'; }

    protected function disconnectEngine(): void { $this->calls[] = 'disconnect'; }

    public function pump(int $budget_ns): int
    {
        $this->pumped[] = $budget_ns;
        ($this->on_pump)?->__invoke($this);

        return 0;
    }

    protected function wakeDescriptor(int $fd): void { $this->woken_by = $fd; }

    protected function releaseWakeDescriptor(): void { $this->woken_by = null; $this->released++; }

    /** @return list<object> */
    public function outbox(): array { return $this->outbox; }
}

/** A second toolkit: the same fake under another class. */
final class OtherFakeSession extends BridgedToolkitSession
{
    public bool $mac = false;

    protected function onMacOs(): bool { return $this->mac; }

    protected function initializeEngine(): void {}

    protected function connectToEngine(): void {}

    protected function disconnectEngine(): void {}

    public function pump(int $budget_ns): int { return 0; }

    protected function wakeDescriptor(int $fd): void {}

    protected function releaseWakeDescriptor(): void {}
}
