<?php

use Surface\NativeWindows\Components\TextArea;
use Venusian\Surface\Tests\Support\Fakes\FakeGroup;
use Venusian\Surface\Tests\Support\Fakes\FakeTextArea;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('mounts a text area at the component name path filling the root', function () {
    $window = new FakeWindow('main');

    $area = new TextArea($window, 'notes', 10, 20, 300, 120, value: 'hello');

    expect($window->view('notes'))->toBeInstanceOf(FakeGroup::class)
        ->and($window->view('notes.area'))->toBeInstanceOf(FakeTextArea::class)
        ->and($area->part('area')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 300, 'height' => 120])
        ->and($area->value())->toBe('hello');
});

it('delegates value reads and writes to the inner area', function () {
    $window = new FakeWindow('main');
    $area = new TextArea($window, 'notes', 0, 0, 300, 120, value: 'hello');

    $area->setValue('world');

    expect($area->value())->toBe('world')
        ->and($area->part('area')->value())->toBe('world');
});

it('setEditable writes through to the inner area', function () {
    $window = new FakeWindow('main');
    $area = new TextArea($window, 'notes', 0, 0, 300, 120);

    $area->setEditable(false);

    expect($area->part('area')->isEditable())->toBeFalse();
});

it('fires onChange from the engine door, not setValue', function () {
    $window = new FakeWindow('main');
    $area = new TextArea($window, 'notes', 0, 0, 300, 120);
    $seen = [];
    $area->onChange(function (string $value) use (&$seen) { $seen[] = $value; });

    $area->setValue('silent');

    expect($seen)->toBe([])
        ->and($area->value())->toBe('silent');

    /** @var FakeTextArea $inner */
    $inner = $area->part('area');
    $inner->edit('typed');

    expect($seen)->toBe(['typed'])
        ->and($area->value())->toBe('typed');
});

it('place stretches the inner area to the new inner size', function () {
    $window = new FakeWindow('main');
    $area = new TextArea($window, 'notes', 0, 0, 300, 120);

    $area->place(0, 0, 400, 200);

    expect($area->part('area')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 400, 'height' => 200]);
});

it('removal frees the root and part names', function () {
    $window = new FakeWindow('main');
    $area = new TextArea($window, 'notes', 0, 0, 300, 120);

    $area->remove();

    expect($window->view('notes'))->toBeNull()
        ->and($window->view('notes.area'))->toBeNull()
        ->and($area->part('area'))->toBeNull();
});
