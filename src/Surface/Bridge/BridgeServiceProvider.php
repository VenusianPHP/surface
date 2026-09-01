<?php

namespace Surface\Bridge;

use Surface\Contracts\Bridge\BridgeManager as ManagerContract;
use Voyager\NutsAndBolts\ServiceProvider;

/**
 * Registers the bridge manager and the 'os-bridge' accessor the OSAppBridge alias resolves.
 */
class BridgeServiceProvider extends ServiceProvider
{
    /**
     * Bind the manager as a singleton so one process keeps one engine session.
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(BridgeManager::class, fn ($app) => new BridgeManager($app));
        $this->app->alias(BridgeManager::class, ManagerContract::class);
        $this->app->singleton('os-bridge', fn ($app) => $app->make(BridgeManager::class));
    }
}
