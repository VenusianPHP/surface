<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Canvas\Canvas;
use Surface\Contracts\Drawing\CPUEngineDriver;
use Surface\Contracts\Drawing\GPUEngineDriver;

/** A Canvas whose engine registry is handed in, so engine() is provable without a container. */
final class FakeEngineCanvas extends Canvas
{
    /** @var array<string, CPUEngineDriver> */
    private array $cpu = [];

    /** @var array<string, GPUEngineDriver> */
    private array $gpu = [];

    /**
     * @param  array<string, CPUEngineDriver>  $cpu
     * @param  array<string, GPUEngineDriver>  $gpu
     */
    public function withEngines(array $cpu = [], array $gpu = []): static
    {
        $this->cpu = $cpu;
        $this->gpu = $gpu;

        return $this;
    }

    protected function resolveCPUEngine(string $name): CPUEngineDriver
    {
        return $this->cpu[$name] ?? throw new \LogicException("No fake CPU engine '{$name}'.");
    }

    protected function resolveGPUEngine(string $name): GPUEngineDriver
    {
        return $this->gpu[$name] ?? throw new \LogicException("No fake GPU engine '{$name}'.");
    }
}
