<?php

namespace Surface\Core;

use Surface\Bridge\ToolkitBridgeDriver;
use Surface\Contracts\Windows\StagedWindowDriver;
use Surface\Contracts\Windows\ToolkitWindowDriver;
use Voyager\Core\ApplicationKernel;

abstract class VisualApplication extends ApplicationKernel
{
    protected array $app_bootstrappers = [];
    protected array $surface_bootstrappers = [];

    public function bootstrappers(): array
    {
        return [
            ...$this->bootstrappers,
            ...$this->surface_bootstrappers,
            ...$this->app_bootstrappers,
        ];
    }

    protected function startup(): void {}

    protected function activate(): void {}

    protected function shutdown(): void {}

    protected function run(): int
    {
        try {
            return parent::run();
        } finally {
            $this->releaseVisualOutputs();
        }
    }

    /**
     * Release every visual output Surface opened, after the app's shutdown().
     */
    protected function releaseVisualOutputs(): void
    {
        try {
            if ($this->app->isResolved('displays')) {
                $this->app['displays']->destroy();
            }
        }
        finally {
            if ($this->app->isResolved('toolkit-bridge')) {
                foreach ($this->app['toolkit-bridge']->getDrivers() as $driver) {
                    if (! $driver instanceof ToolkitBridgeDriver) {
                        continue;
                    }

                    $session = $driver->session();
                    if (is_null($session) || ! $session->connected()) {
                        continue;
                    }

                    try {
                        if ($driver instanceof StagedWindowDriver) {
                            $driver->closeAllStaged();
                        }
                        if ($driver instanceof ToolkitWindowDriver) {
                            $driver->closeAll();
                        }
                    } finally {
                        $session->leaveLoop();
                        $session->disconnect();
                    }
                }
            }
        }
    }
}