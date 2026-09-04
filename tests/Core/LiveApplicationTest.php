<?php

use Surface\Contracts\NativeWindows\WindowableException;
use Voyager\Contracts\IOPools\IOResourceDriver;

/*
|--------------------------------------------------------------------------
| LiveApplication
|--------------------------------------------------------------------------
|
| The LiveApplication is what a sketch actually holds: it pairs one connected
| session with one window driver over the IOPoolDock, and its tick aims the
| idle budget at the OS-level resource then pumps the dock once. Everything
| here is provable with a fake session and a fake driver, no engine present.
|
*/

it('exposes the session and the driver it was built with', function () {
    [$app, , $session, $driver] = liveApp();

    expect($app->getBridgedSession())->toBe($session)
        ->and($app->getWindowService())->toBe($driver)
        ->and($app->get())->toBe($app);
});

it('asks the session for a window and registers what comes back', function () {
    [$app, , $session, $driver] = liveApp();

    $app->provisionWindow('main', 400, 600);

    expect($session->provisions)->toBe([['name' => 'main', 'width' => 400, 'height' => 600]])
        ->and($driver->has('main'))->toBeTrue()
        ->and($driver->get('main')->name())->toBe('main');
});

it('returns the application from a successful provision so calls can chain', function () {
    [$app] = liveApp();

    expect($app->provisionWindow('main', 400, 600))->toBe($app);
});

it('answers false and provisions nothing when the name is already taken', function () {
    [$app, , $session, $driver] = liveApp();
    $app->provisionWindow('main', 400, 600);

    $result = $app->provisionWindow('main', 800, 200);

    expect($result)->toBeFalse()
        ->and($session->provisions)->toHaveCount(1)
        ->and($driver->get('main')->name())->toBe('main');
});

it('provisions several windows under distinct names', function () {
    [$app, , $session, $driver] = liveApp();

    $app->provisionWindow('main', 400, 600);
    $app->provisionWindow('inspector', 200, 300);

    expect($session->provisions)->toHaveCount(2)
        ->and($driver->has('main'))->toBeTrue()
        ->and($driver->has('inspector'))->toBeTrue();
});

it('presents a provisioned window through the driver', function () {
    [$app, , , $driver] = liveApp();
    $app->provisionWindow('main', 400, 600);

    $app->showWindow('main');

    expect($driver->get('main')->isPresenting())->toBeTrue()
        ->and($driver->get('main')->presentations)->toBe(1);
});

it('refuses to show a window that was never provisioned', function () {
    [$app] = liveApp();

    expect(fn () => $app->showWindow('main'))->toThrow(WindowableException::class);
});

it('forwards the tick budget to the session through the os resource', function () {
    [$app, , $session] = liveApp();

    $app->tick(16);

    expect($session->pumps)->toBe([16]);
});

it('ticks with a zero budget by default', function () {
    [$app, , $session] = liveApp();

    $app->tick();

    expect($session->pumps)->toBe([0]);
});

it('pumps every registered resource each tick, not only the os one', function () {
    [$app, $dock] = liveApp();
    $ticks = 0;
    $dock->resource('counter', new class($ticks) implements IOResourceDriver {
        public function __construct(protected int &$count) {}

        public function tick(): void
        {
            $this->count++;
        }
    });

    $app->tick();
    $app->tick();

    expect($ticks)->toBe(2);
});

it('destroys every window, drains the loop and disconnects', function () {
    [$app, , $session, $driver] = liveApp();
    $app->provisionWindow('main', 400, 600);
    $window = $driver->get('main');

    $app->destroy();

    expect($window->destructions)->toBe(1)
        ->and($driver->has('main'))->toBeFalse()
        ->and($session->connected())->toBeFalse()
        ->and($session->engine_disconnections)->toBe(1);
});

it('drains after the windows are gone so the engine sees the closes', function () {
    [$app, , $session] = liveApp();
    $app->provisionWindow('main', 400, 600);

    $app->destroy();

    // One drain from the application after destroyAll(), one from disconnect() itself.
    expect($session->pumps)->toBe([0, 0]);
});

it('still tears the windows down when the session is already disconnected', function () {
    [$app, , $session, $driver] = liveApp(connected: false);
    $app->provisionWindow('main', 400, 600);
    $window = $driver->get('main');

    $app->destroy();

    expect($window->destructions)->toBe(1)
        ->and($driver->has('main'))->toBeFalse()
        ->and($session->pumps)->toBe([])
        ->and($session->engine_disconnections)->toBe(0);
});

it('survives a second destroy', function () {
    [$app, , $session] = liveApp();
    $app->provisionWindow('main', 400, 600);

    $app->destroy();
    $app->destroy();

    expect($session->engine_disconnections)->toBe(1)
        ->and($session->pumps)->toBe([0, 0]);
});
