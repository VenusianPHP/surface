<?php

use Surface\Contracts\NativeWindows\WindowableException;
use Surface\NativeWindows\Drivers\AppKitWindowDriver;
use Surface\NativeWindows\Drivers\GTKWindowDriver;
use Venusian\Surface\Tests\Support\Fakes\ContractOnlyLinuxWindow;
use Venusian\Surface\Tests\Support\Fakes\FakeLinuxWindow;
use Venusian\Surface\Tests\Support\Fakes\FakeMacWindow;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

/*
|--------------------------------------------------------------------------
| Per-engine type guards
|--------------------------------------------------------------------------
|
| Each concrete driver accepts only windows carrying its own OS marker, so a
| session/driver mismatch is caught at registration rather than at the first
| native call. Both drivers must key the registry off the OSWindow contract's
| name() and nothing richer.
|
*/

it('accepts a window carrying the macOS marker', function () {
    $driver = new AppKitWindowDriver([]);
    $window = new FakeMacWindow('main');

    $driver->add($window);

    expect($driver->get('main'))->toBe($window);
});

it('refuses a window that is not a macOS window', function () {
    $driver = new AppKitWindowDriver([]);

    expect(fn () => $driver->add(new FakeWindow('main')))
        ->toThrow(WindowableException::class, 'Windowed instance must implement MacOSWindow');
});

it('refuses a Linux window on the macOS driver', function () {
    $driver = new AppKitWindowDriver([]);

    expect(fn () => $driver->add(new FakeLinuxWindow('main')))
        ->toThrow(WindowableException::class);
});

it('accepts a window carrying the Linux marker', function () {
    $driver = new GTKWindowDriver([]);
    $window = new FakeLinuxWindow('main');

    $driver->add($window);

    expect($driver->get('main'))->toBe($window);
});

it('refuses a window that is not a Linux window', function () {
    $driver = new GTKWindowDriver([]);

    expect(fn () => $driver->add(new FakeWindow('main')))
        ->toThrow(WindowableException::class, 'Windowed instance must implement LinuxOSWindow');
});

it('refuses a macOS window on the Linux driver', function () {
    $driver = new GTKWindowDriver([]);

    expect(fn () => $driver->add(new FakeMacWindow('main')))
        ->toThrow(WindowableException::class);
});

it('keys the registry off the contract, not a public name field', function () {
    $driver = new GTKWindowDriver([]);
    $window = new ContractOnlyLinuxWindow('main');

    $driver->add($window);

    expect($driver->get('main'))->toBe($window);
});

it('keeps the driver config it was built with', function () {
    $mac = new AppKitWindowDriver(['app_name' => 'Demo']);
    $linux = new GTKWindowDriver(['application_id' => 'com.venusian.demo']);

    expect($mac->config)->toBe(['app_name' => 'Demo'])
        ->and($linux->config)->toBe(['application_id' => 'com.venusian.demo']);
});
