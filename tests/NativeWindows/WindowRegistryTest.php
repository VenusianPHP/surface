<?php

use Surface\Contracts\NativeWindows\WindowableException;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;
use Venusian\Surface\Tests\Support\Fakes\FakeWindowDriver;

/*
|--------------------------------------------------------------------------
| Shared registry policy
|--------------------------------------------------------------------------
|
| NativeWindowDriver owns lookup, presentation guarding and teardown for every
| engine. FakeWindowDriver drops the OS marker check so this file asserts that
| shared policy alone; the per-engine guards live in DriverContractTest.
|
*/

it('does not know a window it was never given', function () {
    $driver = new FakeWindowDriver();

    expect($driver->has('main'))->toBeFalse()
        ->and($driver->get('main'))->toBeNull();
});

it('keys the registry by the window name', function () {
    $driver = new FakeWindowDriver();
    $window = new FakeWindow('main');

    $driver->add($window);

    expect($driver->has('main'))->toBeTrue()
        ->and($driver->get('main'))->toBe($window);
});

it('returns the driver from add so calls can chain', function () {
    $driver = new FakeWindowDriver();

    expect($driver->add(new FakeWindow('main')))->toBe($driver);
});

it('replaces a window registered under a name already taken', function () {
    $driver = new FakeWindowDriver();
    $second = new FakeWindow('main');

    $driver->add(new FakeWindow('main'))->add($second);

    expect($driver->get('main'))->toBe($second);
});

it('presents a window that is not yet on screen', function () {
    $driver = new FakeWindowDriver();
    $window = new FakeWindow('main');
    $driver->add($window);

    $driver->presentWindow('main');

    expect($window->presentations)->toBe(1)
        ->and($window->isPresenting())->toBeTrue();
});

it('leaves a window that is already presenting alone', function () {
    $driver = new FakeWindowDriver();
    $window = new FakeWindow('main');
    $driver->add($window);

    $driver->presentWindow('main');
    $driver->presentWindow('main');

    expect($window->presentations)->toBe(1);
});

it('refuses to present a window it does not hold', function () {
    $driver = new FakeWindowDriver();

    expect(fn () => $driver->presentWindow('main'))
        ->toThrow(WindowableException::class, 'Window main does not exists');
});

it('destroys every window it holds and empties the registry', function () {
    $driver = new FakeWindowDriver();
    $first = new FakeWindow('main');
    $second = new FakeWindow('inspector');
    $driver->add($first)->add($second);

    $driver->destroyAll();

    expect($first->destructions)->toBe(1)
        ->and($second->destructions)->toBe(1)
        ->and($driver->has('main'))->toBeFalse()
        ->and($driver->has('inspector'))->toBeFalse();
});

it('destroys nothing when it holds nothing', function () {
    $driver = new FakeWindowDriver();

    $driver->destroyAll();

    expect($driver->has('main'))->toBeFalse();
});

it('can be refilled after a teardown', function () {
    $driver = new FakeWindowDriver();
    $driver->add(new FakeWindow('main'));
    $driver->destroyAll();

    $driver->add(new FakeWindow('main'));

    expect($driver->has('main'))->toBeTrue();
});
