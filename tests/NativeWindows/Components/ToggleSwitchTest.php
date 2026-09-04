<?php

use Surface\NativeWindows\Components\ToggleSwitch;
use Venusian\Surface\Tests\Support\Fakes\FakeGroup;
use Venusian\Surface\Tests\Support\Fakes\FakeToggle;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('mounts a toggle at the component name path filling the root', function () {
    $window = new FakeWindow('main');

    $switch = new ToggleSwitch($window, 'wifi', 10, 20, 48, 28, on: true);

    expect($window->view('wifi'))->toBeInstanceOf(FakeGroup::class)
        ->and($window->view('wifi.switch'))->toBeInstanceOf(FakeToggle::class)
        ->and($switch->part('switch')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 48, 'height' => 28])
        ->and($switch->isOn())->toBeTrue();
});

it('delegates on/off reads and writes to the inner toggle', function () {
    $window = new FakeWindow('main');
    $switch = new ToggleSwitch($window, 'wifi', 0, 0, 48, 28, on: true);

    $switch->setOn(false);

    expect($switch->isOn())->toBeFalse()
        ->and($switch->part('switch')->isOn())->toBeFalse();
});

it('fires onToggle from the engine door, not setOn', function () {
    $window = new FakeWindow('main');
    $switch = new ToggleSwitch($window, 'wifi', 0, 0, 48, 28);
    $seen = [];
    $switch->onToggle(function (bool $on) use (&$seen) { $seen[] = $on; });

    $switch->setOn(true);

    expect($seen)->toBe([])
        ->and($switch->isOn())->toBeTrue();

    /** @var FakeToggle $inner */
    $inner = $switch->part('switch');
    $inner->flip(false);

    expect($seen)->toBe([false])
        ->and($switch->isOn())->toBeFalse();
});

it('place stretches the inner toggle to the new inner size', function () {
    $window = new FakeWindow('main');
    $switch = new ToggleSwitch($window, 'wifi', 0, 0, 48, 28);

    $switch->place(0, 0, 64, 32);

    expect($switch->part('switch')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 64, 'height' => 32]);
});

it('removal frees the root and part names', function () {
    $window = new FakeWindow('main');
    $switch = new ToggleSwitch($window, 'wifi', 0, 0, 48, 28);

    $switch->remove();

    expect($window->view('wifi'))->toBeNull()
        ->and($window->view('wifi.switch'))->toBeNull()
        ->and($switch->part('switch'))->toBeNull();
});
