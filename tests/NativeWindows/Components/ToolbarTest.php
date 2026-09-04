<?php

use Surface\Contracts\NativeWindows\WindowableException;
use Surface\NativeWindows\Components\Toolbar;
use Venusian\Surface\Tests\Support\Fakes\FakeToggleButton;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('starts empty and flows items left to right at natural size, centred', function () {
    $window = new FakeWindow('main');
    $toolbar = new Toolbar($window, 'actions', 0, 0, 400, 44);

    $toolbar->addButton('save', 'Save');
    $toolbar->addButton('load', 'Load');

    // Fake natural size is 80x30; pad 8, gap 8; centred on 44 → y 7.
    expect($toolbar->part('save')->frame())->toBe(['x' => 8, 'y' => 7, 'width' => 80, 'height' => 30])
        ->and($toolbar->part('load')->frame())->toBe(['x' => 96, 'y' => 7, 'width' => 80, 'height' => 30])
        ->and($window->view('actions.save'))->not->toBeNull();
});

it('separators are auto-named hairlines spanning the strip', function () {
    $window = new FakeWindow('main');
    $toolbar = new Toolbar($window, 'actions', 0, 0, 400, 44);

    $toolbar->addButton('save', 'Save');
    $toolbar->addSeparator();
    $toolbar->addButton('run', 'Run');

    expect($toolbar->part('sep.1')->frame())->toBe(['x' => 96, 'y' => 8, 'width' => 1, 'height' => 28])
        ->and($toolbar->part('run')->frame()['x'])->toBe(105);
});

it('toggles land in the flow and keep their pressed state', function () {
    $window = new FakeWindow('main');
    $toolbar = new Toolbar($window, 'actions', 0, 0, 400, 44);

    $toggle = $toolbar->addToggle('bold', 'B', pressed: true);

    expect($toggle)->toBeInstanceOf(FakeToggleButton::class)
        ->and($toggle->isPressed())->toBeTrue();
});

it('a button hook given at add time runs on click', function () {
    $window = new FakeWindow('main');
    $toolbar = new Toolbar($window, 'actions', 0, 0, 400, 44);
    $clicks = 0;

    $toolbar->addButton('save', 'Save', function () use (&$clicks) { $clicks++; });
    $toolbar->part('save')->click();

    expect($clicks)->toBe(1);
});

it('refuses a duplicate item name', function () {
    $window = new FakeWindow('main');
    $toolbar = new Toolbar($window, 'actions', 0, 0, 400, 44);
    $toolbar->addButton('save', 'Save');

    expect(fn () => $toolbar->addToggle('save', 'S'))
        ->toThrow(WindowableException::class, "already has an item 'save'");
});

it('removal kills the strip and every item', function () {
    $window = new FakeWindow('main');
    $toolbar = new Toolbar($window, 'actions', 0, 0, 400, 44);
    $toolbar->addButton('save', 'Save');
    $toolbar->addSeparator();

    $toolbar->remove();

    expect($window->view('actions'))->toBeNull()
        ->and($window->view('actions.save'))->toBeNull()
        ->and($window->view('actions.sep.1'))->toBeNull();
});
