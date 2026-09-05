<?php

use Surface\NativeWindows\Components\Drawer;
use Surface\NativeWindows\Components\DrawerSide;
use Venusian\Surface\Tests\Support\Fakes\FakeGroup;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('starts hidden with a padded body that fills the inner frame', function () {
    $window = new FakeWindow('main');
    $drawer = new Drawer($window, 'drawer', 10, 0, 240, 400, DrawerSide::LEFT);

    expect($window->view('drawer'))->toBeInstanceOf(FakeGroup::class)
        ->and($window->view('drawer.body'))->toBeInstanceOf(FakeGroup::class)
        ->and($drawer->isOpen())->toBeFalse()
        ->and($drawer->isVisible())->toBeFalse()
        ->and($drawer->side())->toBe(DrawerSide::LEFT)
        ->and($drawer->part('body')->frame())->toBe(['x' => 12, 'y' => 12, 'width' => 216, 'height' => 376]);
});

it('open shows the root and fires onOpen; close hides and fires onClose', function () {
    $window = new FakeWindow('main');
    $drawer = new Drawer($window, 'drawer', 0, 0, 240, 400, DrawerSide::RIGHT);
    $events = [];
    $drawer->onOpen(function () use (&$events) { $events[] = 'open'; });
    $drawer->onClose(function () use (&$events) { $events[] = 'close'; });

    $drawer->open();

    expect($drawer->isOpen())->toBeTrue()
        ->and($drawer->isVisible())->toBeTrue()
        ->and($events)->toBe(['open']);

    $drawer->close();

    expect($drawer->isOpen())->toBeFalse()
        ->and($drawer->isVisible())->toBeFalse()
        ->and($events)->toBe(['open', 'close']);
});

it('the sketch conjures content into the body group', function () {
    $window = new FakeWindow('main');
    $drawer = new Drawer($window, 'drawer', 0, 0, 240, 400, DrawerSide::TOP);

    $label = $drawer->body()->label('caption', 'Menu', 0, 0, 100, 20);

    expect($window->view('caption'))->toBe($label)
        ->and($label->hostedBy())->toBe($drawer->part('body'));
});

it('side is recorded; it does not move the root', function () {
    $window = new FakeWindow('main');
    $drawer = new Drawer($window, 'drawer', 80, 40, 200, 300, DrawerSide::BOTTOM);

    expect($drawer->side())->toBe(DrawerSide::BOTTOM)
        ->and($drawer->frame())->toBe(['x' => 80, 'y' => 40, 'width' => 200, 'height' => 300]);
});

it('place re-arranges the body against the new inner size', function () {
    $window = new FakeWindow('main');
    $drawer = new Drawer($window, 'drawer', 0, 0, 240, 400, DrawerSide::LEFT);

    $drawer->place(0, 0, 180, 200);

    expect($drawer->part('body')->frame())->toBe(['x' => 12, 'y' => 12, 'width' => 156, 'height' => 176]);
});

it('removal frees the root and drawer.body', function () {
    $window = new FakeWindow('main');
    $drawer = new Drawer($window, 'drawer', 0, 0, 240, 400, DrawerSide::LEFT);
    $drawer->body()->label('caption', 'Menu', 0, 0, 100, 20);

    $drawer->remove();

    expect($window->view('drawer'))->toBeNull()
        ->and($window->view('drawer.body'))->toBeNull()
        ->and($window->view('caption'))->toBeNull();
});
