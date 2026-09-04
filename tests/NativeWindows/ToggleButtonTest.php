<?php

use Surface\Contracts\NativeWindows\Events\View\Toggled;
use Venusian\Surface\Tests\Support\Fakes\FakeToggleButton;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('conjures a toggle button with label and state, and places it at once', function () {
    $window = new FakeWindow('main');

    $button = $window->toggleButton('bold', 'B', false, 10, 20, 40, 30);

    expect($button)->toBeInstanceOf(FakeToggleButton::class)
        ->and($button->label())->toBe('B')
        ->and($button->isPressed())->toBeFalse()
        ->and($button->applied_frames)->toBe([[10, 20, 40, 30]]);
});

it('setPressed writes through only on change, setLabel always', function () {
    $window = new FakeWindow('main');
    $button = $window->toggleButton('bold', 'B', false, 0, 0, 40, 30);

    $button->setPressed(true);
    $button->setPressed(true);
    $button->setLabel('Bold');

    expect($button->applied_pressed)->toBe([true])
        ->and($button->label())->toBe('Bold')
        ->and($button->applied_labels)->toBe(['Bold']);
});

it('an engine press updates the state, invokes the hook, and rides the dock', function () {
    $dock = bareDock();
    $window = new FakeWindow('main');
    $window->setPool($dock);
    $seen = [];
    $button = $window->toggleButton('bold', 'B', false, 0, 0, 40, 30)
        ->onToggle(function (bool $pressed) use (&$seen) { $seen[] = $pressed; });

    $button->press(true);
    $button->press(false);

    $mail = $dock->drain()->filter(fn (object $mail) => $mail instanceof Toggled)->values();
    expect($button->isPressed())->toBeFalse()
        ->and($seen)->toBe([true, false])
        ->and($mail)->toHaveCount(2)
        ->and($mail->first()->name)->toBe('main.bold.toggled');
});
