<?php

namespace Surface\Core\Providers;

use Voyager\IOPools\IOPoolDock;
use Surface\Core\LiveApplication;
use Voyager\Contracts\Vessel\Vessel;
use Surface\Bridge\BridgeServiceProvider;
use Surface\Core\IOPools\OSLevelResourceDriver;
use Voyager\NutsAndBolts\AggregateServiceProvider;
use Surface\NativeWindows\NativeWindowsServiceProvider;

class SurfaceServiceProvider extends AggregateServiceProvider
{
    protected array $providers = [
        BridgeServiceProvider::class,
        NativeWindowsServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton('live-app', fn ($app) => $app->make(LiveApplication::class));
        $this->app->singleton(OSLevelResourceDriver::class, function (Vessel $app) {
            /** @var IOPoolDock $dock */
            $dock = $this->app->make('io-pool');
            $session = $app->get('os-bridge')->connect();
            $window_service = app('native-window')->driver();
            $driver = new OSLevelResourceDriver($dock, $session, $window_service);
            $dock->resource('os', $driver);

            return $driver;
        });

        $this->app->singleton(LiveApplication::class, fn (Vessel $app) => new LiveApplication(
            $app->make('io-pool'),
            $app->get('os-bridge')->connect(),
            app('native-window')->driver(),
        ));
    }

    public function boot(): void
    {
        $this->app->make(OSLevelResourceDriver::class);
    }
}