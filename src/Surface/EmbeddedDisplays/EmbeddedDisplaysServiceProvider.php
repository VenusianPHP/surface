<?php

namespace Surface\EmbeddedDisplays;

use Surface\Contracts\EmbeddedDisplays\EmbeddedDisplayException;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\ServiceProvider;

class EmbeddedDisplaysServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The circuit catalog and the loop are looked up when first needed:
        // neither has to be installed for a display over a chip built by hand.
        $this->app->registerSingleton('displays', fn (FrameworkCore $app) => new EmbeddedDisplayManager(
            $app->make('framebuffers'),
            $app->make('config')->get('embedded-displays.defaults'),
            fn (): object => $app->has('circuit') ? $app->get('circuit') : throw EmbeddedDisplayException::noCatalog(),
            function (object $mail) use ($app): void {
                if ($app->has('event-loop')) {
                    $app->get('event-loop')->post($mail);
                }
            },
        ));
        $this->app->alias('displays', EmbeddedDisplayManager::class);
    }
}
