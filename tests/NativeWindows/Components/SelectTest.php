<?php

use Surface\NativeWindows\Components\Select;
use Venusian\Surface\Tests\Support\Fakes\FakeDropdown;
use Venusian\Surface\Tests\Support\Fakes\FakeGroup;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('mounts a dropdown at the component name path filling the root', function () {
    $window = new FakeWindow('main');

    $select = new Select($window, 'planet', 10, 20, 160, 28, options: ['Mars', 'Venus'], selected: 1);

    expect($window->view('planet'))->toBeInstanceOf(FakeGroup::class)
        ->and($window->view('planet.dropdown'))->toBeInstanceOf(FakeDropdown::class)
        ->and($select->part('dropdown')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 160, 'height' => 28])
        ->and($select->options())->toBe(['Mars', 'Venus'])
        ->and($select->selectedIndex())->toBe(1)
        ->and($select->selectedOption())->toBe('Venus');
});

it('delegates options and selection reads and writes to the inner dropdown', function () {
    $window = new FakeWindow('main');
    $select = new Select($window, 'planet', 0, 0, 160, 28, options: ['Mars', 'Venus'], selected: 0);

    $select->setOptions(['Io', 'Titan'], 1);

    expect($select->options())->toBe(['Io', 'Titan'])
        ->and($select->selectedOption())->toBe('Titan')
        ->and($select->part('dropdown')->options())->toBe(['Io', 'Titan']);

    $select->select(0);

    expect($select->selectedIndex())->toBe(0)
        ->and($select->selectedOption())->toBe('Io');
});

it('fires onSelect from the engine door, not select', function () {
    $window = new FakeWindow('main');
    $select = new Select($window, 'planet', 0, 0, 160, 28, options: ['Mars', 'Venus'], selected: 0);
    $seen = [];
    $select->onSelect(function (int $index, ?string $option) use (&$seen) { $seen[] = [$index, $option]; });

    $select->select(1);

    expect($seen)->toBe([])
        ->and($select->selectedOption())->toBe('Venus');

    /** @var FakeDropdown $inner */
    $inner = $select->part('dropdown');
    $inner->pick(0);

    expect($seen)->toBe([[0, 'Mars']])
        ->and($select->selectedIndex())->toBe(0)
        ->and($select->selectedOption())->toBe('Mars');
});

it('place stretches the inner dropdown to the new inner size', function () {
    $window = new FakeWindow('main');
    $select = new Select($window, 'planet', 0, 0, 160, 28, options: ['Mars']);

    $select->place(0, 0, 240, 32);

    expect($select->part('dropdown')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 240, 'height' => 32]);
});

it('removal frees the root and part names', function () {
    $window = new FakeWindow('main');
    $select = new Select($window, 'planet', 0, 0, 160, 28, options: ['Mars']);

    $select->remove();

    expect($window->view('planet'))->toBeNull()
        ->and($window->view('planet.dropdown'))->toBeNull()
        ->and($select->part('dropdown'))->toBeNull();
});
