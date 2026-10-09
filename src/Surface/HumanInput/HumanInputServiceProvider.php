<?php

namespace Surface\HumanInput;

use Voyager\Contracts\Core\FrameworkCore;
use Voyager\Contracts\IOPools\Loop;
use Voyager\NutsAndBolts\ServiceProvider;

class HumanInputServiceProvider extends ServiceProvider
{
    /**
     * One manager per application. Its frame is as long as the sketch refresh rate says;
     * its mail goes to the loop once there is one.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->registerSingleton('human-input', fn (FrameworkCore $app) => new HumanInputManager(
            $app->get('toolkit-bridge'),
            new InputFrame(fn (): int => (int) (1_000_000_000 / max((float) config('sketches.refresh_rate', 60), 1.0))),
            device_os_family(),
            config('human-input.pads.'.device_os_family()),
            function (object $mail) use ($app): void {
                if ($app->isResolved('event-loop')) {
                    $app->get('event-loop')->post($mail);
                }
            },
        ));
        $this->app->alias('human-input', HumanInputManager::class);
    }

    /**
     * Input joins the loop when the loop is first resolved: an application that never
     * runs a loop never makes one for input.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->callAfterResolving('event-loop', fn (Loop $loop) => HumanInputPoller::join($loop, $this->app->get('human-input')));
    }
}
