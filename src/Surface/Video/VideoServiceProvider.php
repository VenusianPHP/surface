<?php

namespace Surface\Video;

use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\ServiceProvider;

class VideoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->registerSingleton('video', fn (FrameworkCore $app) => new VideoManager($app));
        $this->app->alias('video', VideoManager::class);
    }
}
