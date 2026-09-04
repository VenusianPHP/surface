<?php

namespace Surface\Core\IOPools;

use Surface\Contracts\Bridge\BridgedOSSession;
use Surface\Contracts\Core\OSLevelResourceDriver as ResourceDriverContract;
use Surface\Contracts\NativeWindows\OSWindowDriver;
use Voyager\Contracts\IOPools\PoolPump;

class OSLevelResourceDriver implements ResourceDriverContract
{
    /**
     * How long the native pump may block waiting for OS events, per tick.
     * The wait wakes instantly on input, so this is idle time, not latency.
     * The loop owner re-aims it every tick via waitBudget().
     */
    protected int $wait_budget_ms = 0;

    public function __construct(
        public readonly PoolPump $io_pool,
        public readonly BridgedOSSession $session,
        public readonly OSWindowDriver   $window_service,
    ) {}

    public function waitBudget(int $ms): static
    {
        $this->wait_budget_ms = max(0, $ms);

        return $this;
    }

    public function tick(): void
    {
        $this->session->pump($this->wait_budget_ms);

        foreach ($this->window_service->all() as $window) {
            $window->syncLayout();
        }
    }
}