<?php

namespace Surface\NativeWindows;

use Voyager\Contracts\Vessel\Vessel;
use Voyager\NutsAndBolts\ServiceProvider;

class NativeWindowsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__, 3).'/config/windows.php',
            'windows',
        );

        $this->app->singleton(WindowManager::class, fn (Vessel $app) => new WindowManager($app));

        $this->app->singleton('native-window', fn ($app) => $app->make(WindowManager::class));
    }

    public function boot(): void
    {
        $this->publishes([
            dirname(__DIR__, 3).'/config/windows.php' => $this->app->configPath('windows.php'),
        ], 'surface-config');
    }
}