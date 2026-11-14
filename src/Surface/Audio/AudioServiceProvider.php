<?php

namespace Surface\Audio;

use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\ServiceProvider;

class AudioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->registerSingleton('audio', fn (FrameworkCore $app) => new AudioManager($app));
        $this->app->alias('audio', AudioManager::class);
    }
}
