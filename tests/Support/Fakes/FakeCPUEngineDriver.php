<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Contracts\Drawing\CPUDrawTarget;
use Surface\Contracts\Drawing\CPUEngine;
use Surface\Contracts\Drawing\CPUEngineDriver;
use Surface\Contracts\Drawing\CPUHost;

/** A CPUEngineDriver that remembers every host and answers whatever target a test injects. */
final class FakeCPUEngineDriver implements CPUEngineDriver
{
    /** @var list<CPUHost> */
    public array $hosts = [];

    public function __construct(public CPUEngine $engine = CPUEngine::DIRTY, public ?CPUDrawTarget $target = null) {}

    public function engine(): CPUEngine
    {
        return $this->engine;
    }

    public function attach(CPUHost $host): CPUDrawTarget
    {
        $this->hosts[] = $host;

        return $this->target ?? throw new \LogicException('FakeCPUEngineDriver has no target to hand back.');
    }
}
