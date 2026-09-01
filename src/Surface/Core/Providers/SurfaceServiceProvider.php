<?php

namespace Surface\Core\Providers;

use Surface\Bridge\BridgeServiceProvider;
use Surface\Core\ProgramShuttle;
use Surface\NativeWindows\NativeWindowsServiceProvider;
use Surface\NativeWindows\WindowManager;
use Voyager\NutsAndBolts\AggregateServiceProvider;
use Voyager\Vessel\Vessel;

class SurfaceServiceProvider extends AggregateServiceProvider
{
    protected array $providers = [
        BridgeServiceProvider::class,
        NativeWindowsServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(ProgramShuttle::class, fn (Vessel $app) => new ProgramShuttle(
            $app->get('os-bridge')->connect(),
            app('native-window')->driver(),
        ));

        $this->app->singleton('os-program', fn ($app) => $app->make(ProgramShuttle::class));

        // The program's HTTP pool under its own class name, so packages
        // that only know voyager/io-pools (an API client, say) dispatch
        // through the same pool the sketch's callHttp() rides.
        $this->app->singleton(\Voyager\IOPools\HttpPool::class, fn ($app) => $app->make('os-program')->httpPool());
    }
}