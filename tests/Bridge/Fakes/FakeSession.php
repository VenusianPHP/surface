<?php

namespace Venusian\Surface\Tests\Bridge\Fakes;

use Surface\Bridge\BridgedOSSession;
use Surface\Contracts\NativeWindows\OSWindow;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

/** Counts every engine hook the abstract session fires, so the state machine can be asserted without an engine. */
final class FakeSession extends BridgedOSSession
{
    public int $initializations = 0;

    public int $engine_connections = 0;

    public int $engine_disconnections = 0;

    /** @var list<int> Budget passed to each pumpEngine() call, in order. */
    public array $pumps = [];

    /** @var list<array{name: string, width: int, height: int}> Every window request, in order. */
    public array $provisions = [];

    /** Value pumpEngine() hands back, so forwarding can be told apart from the disconnected 0. */
    public int $pump_result = 7;

    protected function initializeEngine(): void
    {
        $this->initializations++;
    }

    protected function connectToEngine(): void
    {
        $this->engine_connections++;
    }

    protected function disconnectEngine(): void
    {
        $this->engine_disconnections++;
    }

    protected function pumpEngine(int $budget_ms): int
    {
        $this->pumps[] = $budget_ms;

        return $this->pump_result;
    }

    /**
     * Mint a fake window and record what was asked for.
     *
     * The abstract session adds no policy of its own here — window minting is the
     * engine package's job — so the fake only has to answer the contract.
     */
    public function provisionNewWindow(string $name, int $width, int $height): OSWindow
    {
        $this->provisions[] = ['name' => $name, 'width' => $width, 'height' => $height];

        return new FakeWindow($name);
    }
}
