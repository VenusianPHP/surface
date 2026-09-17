<?php

namespace Surface\HumanInput;

use Voyager\Contracts\Vessel\Vessel;
use Voyager\NutsAndBolts\ServiceProvider;

class HumanInputServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__, 3).'/config/human-input.php',
            'human-input',
        );

        $this->app->singleton(HumanInputManager::class, fn (Vessel $app) => new HumanInputManager($app));

        $this->app->singleton('human-input', fn ($app) => $app->make(HumanInputManager::class));
    }

    public function boot(): void
    {
        $this->publishes([
            dirname(__DIR__, 3).'/config/human-input.php' => $this->app->configPath('human-input.php'),
        ], 'surface-config');

        // After every provider has booted, so 'os' is already on the dock and ticks first.
        $this->app->booted(function (): void {
            $dock = $this->app->make('io-pool');
            $dock->resource('input', new HumanInputResourceDriver($dock, $this->app->make(HumanInputManager::class)));
        });
    }
}
