<?php

namespace Surface\EmbeddedDisplays;

use Voyager\Contracts\Vessel\Vessel;
use Voyager\NutsAndBolts\ServiceProvider;

class EmbeddedDisplaysServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__, 3).'/config/embedded-displays.php',
            'embedded-displays',
        );

        $this->app->singleton(EmbeddedDisplayManager::class, fn (Vessel $app) => new EmbeddedDisplayManager($app));

        $this->app->singleton('embedded-displays', fn ($app) => $app->make(EmbeddedDisplayManager::class));
    }

    public function boot(): void
    {
        $this->publishes([
            dirname(__DIR__, 3).'/config/embedded-displays.php' => $this->app->configPath('embedded-displays.php'),
        ], 'surface-config');

        // After every provider has booted, so the dock already holds whatever ticks first.
        $this->app->booted(function (): void {
            $this->app->make('io-pool')->resource('displays', new EmbeddedDisplayResourceDriver($this->app->make(EmbeddedDisplayManager::class)));
        });
    }
}
