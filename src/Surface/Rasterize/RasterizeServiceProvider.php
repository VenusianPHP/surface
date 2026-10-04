<?php

namespace Surface\Rasterize;

use ReflectionException;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\ServiceProvider;

class RasterizeServiceProvider extends ServiceProvider
{
    /**
     * @return void
     * @throws ReflectionException
     */
    public function register(): void
    {
        $this->app->registerSingleton('rasterize', fn (FrameworkCore $app) => new RasterizeManager($app));
        $this->app->alias('rasterize', RasterizeManager::class);
    }

    public function boot(): void
    {

    }
}
