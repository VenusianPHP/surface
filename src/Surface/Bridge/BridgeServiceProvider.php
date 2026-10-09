<?php

namespace Surface\Bridge;

use ReflectionException;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\ServiceProvider;

class BridgeServiceProvider extends ServiceProvider
{
    /**
     * The toolkit manager is the one place a toolkit session comes from: drivers cache their
     * session, so resolving it again never starts a second engine.
     *
     * @return void
     * @throws ReflectionException
     */
    public function register(): void
    {
        $this->app->registerSingleton('toolkit-bridge', fn (FrameworkCore $app) => new ToolkitManager($app));
        $this->app->alias('toolkit-bridge', ToolkitManager::class);
    }
}
