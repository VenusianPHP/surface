<?php

namespace Surface\Stage;

use Surface\Contracts\Stage\StagedWindow as StagedWindowContract;
use Surface\Contracts\Stage\StageSession as StageSessionContract;
use Voyager\Contracts\IOPools\IOResourceDriver;
use Voyager\IOPools\IOPoolDock;

/**
 * The dock resource for one stage host: pump the host, then run one frame on
 * each of its open stages. A host that shares the native bridge's pump
 * (AppKit) is not pumped again while the 'os' resource is on the dock — that
 * resource already drained NSApp this tick. The pump never waits: the 'os'
 * resource owns the tick's one idle wait.
 */
final class StageResourceDriver implements IOResourceDriver
{
    /** @var array<string, StagedWindowContract> */
    protected array $stages = [];

    public function __construct(
        protected IOPoolDock $dock,
        public readonly StageSessionContract $session,
    ) {}

    public function track(StagedWindowContract $stage): void
    {
        $this->stages[$stage->name()] = $stage;
    }

    /** @return array<string, StagedWindowContract> */
    public function stages(): array
    {
        return $this->stages;
    }

    public function tick(): void
    {
        if (! ($this->session->sharesNativePump() && ! is_null($this->dock->os()))) {
            $this->session->pump(0);
        }

        foreach ($this->stages as $name => $stage) {
            if (! $stage->isOpen()) {
                unset($this->stages[$name]);

                continue;
            }

            $stage->renderFrame();
        }
    }
}
