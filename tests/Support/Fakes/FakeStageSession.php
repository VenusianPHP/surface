<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Contracts\Drawing\CPUEngineDriver;
use Surface\Contracts\Drawing\CPUHost;
use Surface\Contracts\Drawing\GPUEngineDriver;
use Surface\Contracts\Drawing\GPUHost;
use Surface\Contracts\Stage\StageFit;
use Surface\Contracts\Stage\StageHost;
use Surface\Stage\CPUStagedWindow;
use Surface\Stage\StagedWindow;
use Surface\Stage\StageSession;

/** Counts every hook the abstract session fires; mints FakeStagedWindows through the engine's attach(). */
final class FakeStageSession extends StageSession
{
    public int $initializations = 0;

    public int $engine_connections = 0;

    public int $engine_disconnections = 0;

    /** @var list<int> */
    public array $pumps = [];

    /** @var list<FakeStagedWindow> */
    public array $minted = [];

    public int $pump_result = 3;

    /** When set, disconnectEngine() counts the call, then throws this. */
    public ?\Throwable $disconnect_failure = null;

    /** Runs inside disconnectEngine(), before any failure — lets a test observe teardown order. */
    public ?\Closure $on_disconnect = null;

    public function __construct(
        public StageHost $stage_host = StageHost::SDL3,
        public bool $shares_native_pump = false,
        public bool $owns_native_pump = false,
        public float $scale = 1.0,
    ) {}

    public function host(): StageHost
    {
        return $this->stage_host;
    }

    public function sharesNativePump(): bool
    {
        return $this->shares_native_pump;
    }

    public function ownsNativePump(): bool
    {
        return $this->owns_native_pump;
    }

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

        if (! is_null($this->on_disconnect)) {
            ($this->on_disconnect)();
        }

        if (! is_null($this->disconnect_failure)) {
            throw $this->disconnect_failure;
        }
    }

    protected function pumpEngine(int $budget_ms): int
    {
        $this->pumps[] = $budget_ms;

        return $this->pump_result;
    }

    protected function mintStage(string $name, GPUEngineDriver $engine, int $width, int $height): StagedWindow
    {
        $attachment = $engine->attach(new GPUHost(0, $width, $height, $this->scale));

        return $this->minted[] = new FakeStagedWindow($name, $engine->engine(), $attachment->executor, $width, $height, $this->scale);
    }

    /** @var list<FakeCPUStagedWindow> */
    public array $cpu_minted = [];

    public bool $refuses_cpu = false;

    protected function mintCPUStage(string $name, CPUEngineDriver $engine, CPUHost $canvas, int $width, int $height, StageFit $fit): CPUStagedWindow
    {
        if ($this->refuses_cpu) {
            return parent::mintCPUStage($name, $engine, $canvas, $width, $height, $fit);
        }

        return $this->cpu_minted[] = new FakeCPUStagedWindow($name, $engine->attach($canvas), $width, $height, $this->scale, $fit);
    }
}
