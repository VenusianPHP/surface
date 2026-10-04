<?php

namespace Surface\Images;

use ReflectionException;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\ServiceProvider;

class ImagesServiceProvider extends ServiceProvider
{
    /**
     * @return void
     * @throws ReflectionException
     */
    public function register(): void
    {
        $this->app->registerSingleton('images', fn (FrameworkCore $app) => new ImagesManager($app));
        $this->app->alias('images', ImagesManager::class);
    }

    public function boot(): void
    {

    }
}
