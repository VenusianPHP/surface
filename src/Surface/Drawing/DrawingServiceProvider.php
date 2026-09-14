<?php

namespace Surface\Drawing;

use Voyager\Contracts\Vessel\Vessel;
use Voyager\NutsAndBolts\ServiceProvider;

class DrawingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__, 3).'/config/gpu.php',
            'gpu',
        );

        $this->app->singleton(GPUEngineManager::class, fn (Vessel $app) => new GPUEngineManager($app));

        $this->app->singleton('gpu-engines', fn ($app) => $app->make(GPUEngineManager::class));
    }

    public function boot(): void
    {
        $this->publishes([
            dirname(__DIR__, 3).'/config/gpu.php' => $this->app->configPath('gpu.php'),
        ], 'surface-config');
    }
}
