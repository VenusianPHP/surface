<?php

declare(strict_types=1);

use Surface\Bridge\ToolkitBridgeDriver;
use Venusian\Surface\Tests\Fixtures\FakeSession;

it('hands out every registered toolkit, connected or not', function (): void {
    $bridge = bridgeOn('mac');
    $bridge->extend('gtk', fn (): FakeBridgeDriver => new FakeBridgeDriver(new FakeSession()));
    $bridge->extend('sdl3', fn (): FakeBridgeDriver => new FakeBridgeDriver(new FakeSession()));

    $gtk = $bridge->driver('gtk');
    $gtk->connect();

    expect($bridge->driver('sdl3'))->toBeInstanceOf(FakeBridgeDriver::class)
        ->and($bridge->driver('gtk'))->toBe($gtk)
        ->and(array_keys($bridge->getDrivers()))->toBe(['gtk', 'sdl3']);
});

it('reports its session without making one', function (): void {
    $driver = new class extends ToolkitBridgeDriver {
        public function __construct() {}

        public function connect(): FakeSession
        {
            /** @var FakeSession */
            return $this->session ??= (new FakeSession())->connect();
        }
    };

    expect($driver->session())->toBeNull();

    $session = $driver->connect();

    expect($driver->session())->toBe($session);
});
