<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Contracts\Drawing\DrawingException;
use Surface\Contracts\Drawing\GPUAttachment;
use Surface\Contracts\Drawing\GPUEngine;
use Surface\Contracts\Drawing\GPUEngineDriver;
use Surface\Contracts\Drawing\GPUHost;
use Surface\Contracts\Drawing\SurfaceKind;

/**
 * A GPUEngineDriver that hands back a FakeExecutor and remembers every host it
 * saw. A GL_CONTEXT fake refuses a host with no gl, exactly like the real engine.
 */
final class FakeGPUEngineDriver implements GPUEngineDriver
{
    /** @var list<GPUHost> */
    public array $hosts = [];

    public FakeExecutor $executor;

    public function __construct(
        public GPUEngine $engine = GPUEngine::METAL,
        ?FakeExecutor $executor = null,
        public SurfaceKind $surface_kind = SurfaceKind::LAYER,
    ) {
        $this->executor = $executor ?? new FakeExecutor();
    }

    public function engine(): GPUEngine
    {
        return $this->engine;
    }

    public function surfaceKind(): SurfaceKind
    {
        return $this->surface_kind;
    }

    public function attach(GPUHost $host): GPUAttachment
    {
        if ($this->surface_kind === SurfaceKind::GL_CONTEXT && is_null($host->gl)) {
            throw new DrawingException('a GL engine needs a GLSurface on the host');
        }

        $this->hosts[] = $host;
        $this->executor = new FakeExecutor();
        $this->executor->gl = $host->gl;
        $this->executor->resize((int) round($host->width * $host->scale), (int) round($host->height * $host->scale));

        return new GPUAttachment($this->executor, 0, '');
    }
}
