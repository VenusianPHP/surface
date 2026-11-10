<?php

namespace Surface\Bridge;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\ServiceProvider;
use Voyager\Contracts\IOPools\Loop as LoopInterface;

class BridgeServiceProvider extends ServiceProvider
{
    /**
     * The toolkit manager is the one place a toolkit session comes from: drivers cache their
     * session, so resolving it again never starts a second engine.
     *
     * @return void
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function register(): void
    {
        $this->app->registerSingleton('toolkit-bridge', fn (FrameworkCore $app) => new ToolkitManager($app));
        $this->app->alias('toolkit-bridge', ToolkitManager::class);

        // In an application run, a session that connected before the loop existed joins it now.
        $this->callAfterResolving('event-loop', function (LoopInterface $loop): void {
            if (! $this->app->isApplicationRunning()) {
                return;
            }

            foreach ($this->app->get('toolkit-bridge')->getDrivers() as $driver) {
                $session = $driver instanceof ToolkitBridgeDriver ? $driver->session() : null;
                if ($session?->connected()) {
                    $session->joinLoop($loop);
                }
            }
        });
    }
}
