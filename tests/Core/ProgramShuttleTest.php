<?php

use Surface\Contracts\NativeWindows\WindowableException;
use Surface\Core\ProgramShuttle;
use Venusian\Surface\Tests\Bridge\Fakes\FakeSession;
use Venusian\Surface\Tests\Support\Fakes\FakeWindowDriver;

/*
|--------------------------------------------------------------------------
| ProgramShuttle
|--------------------------------------------------------------------------
|
| The shuttle is what a sketch actually holds: it pairs one connected session
| with one window driver and is the only place the two meet. Everything here is
| provable with a fake session and a fake driver, no engine present.
|
*/

function shuttle(bool $connected = true): array
{
    $session = new FakeSession();

    if ($connected) {
        $session->connect();
    }

    $driver = new FakeWindowDriver();

    return [new ProgramShuttle($session, $driver), $session, $driver];
}

it('exposes the session and the driver it was built with', function () {
    [$program, $session, $driver] = shuttle();

    expect($program->getBridgedSession())->toBe($session)
        ->and($program->getWindowService())->toBe($driver)
        ->and($program->get())->toBe($program);
});

it('asks the session for a window and registers what comes back', function () {
    [$program, $session, $driver] = shuttle();

    $program->provisionWindow('main', 400, 600);

    expect($session->provisions)->toBe([['name' => 'main', 'width' => 400, 'height' => 600]])
        ->and($driver->has('main'))->toBeTrue()
        ->and($driver->get('main')->name())->toBe('main');
});

it('returns the shuttle from a successful provision so calls can chain', function () {
    [$program] = shuttle();

    expect($program->provisionWindow('main', 400, 600))->toBe($program);
});

it('answers false and provisions nothing when the name is already taken', function () {
    [$program, $session, $driver] = shuttle();
    $program->provisionWindow('main', 400, 600);

    $result = $program->provisionWindow('main', 800, 200);

    expect($result)->toBeFalse()
        ->and($session->provisions)->toHaveCount(1)
        ->and($driver->get('main')->name())->toBe('main');
});

it('provisions several windows under distinct names', function () {
    [$program, $session, $driver] = shuttle();

    $program->provisionWindow('main', 400, 600);
    $program->provisionWindow('inspector', 200, 300);

    expect($session->provisions)->toHaveCount(2)
        ->and($driver->has('main'))->toBeTrue()
        ->and($driver->has('inspector'))->toBeTrue();
});

it('presents a provisioned window through the driver', function () {
    [$program, , $driver] = shuttle();
    $program->provisionWindow('main', 400, 600);

    $program->showWindow('main');

    expect($driver->get('main')->isPresenting())->toBeTrue()
        ->and($driver->get('main')->presentations)->toBe(1);
});

it('refuses to show a window that was never provisioned', function () {
    [$program] = shuttle();

    expect(fn () => $program->showWindow('main'))->toThrow(WindowableException::class);
});

it('forwards the tick budget to the session', function () {
    [$program, $session] = shuttle();

    $program->tick(16);

    expect($session->pumps)->toBe([16]);
});

it('ticks with a zero budget by default', function () {
    [$program, $session] = shuttle();

    $program->tick();

    expect($session->pumps)->toBe([0]);
});

it('destroys every window, drains the loop and disconnects', function () {
    [$program, $session, $driver] = shuttle();
    $program->provisionWindow('main', 400, 600);
    $window = $driver->get('main');

    $program->destroy();

    expect($window->destructions)->toBe(1)
        ->and($driver->has('main'))->toBeFalse()
        ->and($session->connected())->toBeFalse()
        ->and($session->engine_disconnections)->toBe(1);
});

it('drains after the windows are gone so the engine sees the closes', function () {
    [$program, $session] = shuttle();
    $program->provisionWindow('main', 400, 600);

    $program->destroy();

    // One drain from the shuttle after destroyAll(), one from disconnect() itself.
    expect($session->pumps)->toBe([0, 0]);
});

it('still tears the windows down when the session is already disconnected', function () {
    [$program, $session, $driver] = shuttle(connected: false);
    $program->provisionWindow('main', 400, 600);
    $window = $driver->get('main');

    $program->destroy();

    expect($window->destructions)->toBe(1)
        ->and($driver->has('main'))->toBeFalse()
        ->and($session->pumps)->toBe([])
        ->and($session->engine_disconnections)->toBe(0);
});

it('survives a second destroy', function () {
    [$program, $session] = shuttle();
    $program->provisionWindow('main', 400, 600);

    $program->destroy();
    $program->destroy();

    expect($session->engine_disconnections)->toBe(1)
        ->and($session->pumps)->toBe([0, 0]);
});
