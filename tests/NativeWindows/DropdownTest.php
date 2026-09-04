<?php

use Surface\Contracts\NativeWindows\Events\View\SelectionChanged;
use Venusian\Surface\Tests\Support\Fakes\FakeDropdown;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('conjures a dropdown with options and selection, and places it at once', function () {
    $window = new FakeWindow('main');

    $dropdown = $window->dropdown('planet', ['Mars', 'Venus', 'Europa'], 1, 10, 20, 160, 28);

    expect($dropdown)->toBeInstanceOf(FakeDropdown::class)
        ->and($dropdown->options())->toBe(['Mars', 'Venus', 'Europa'])
        ->and($dropdown->selectedIndex())->toBe(1)
        ->and($dropdown->selectedOption())->toBe('Venus')
        ->and($dropdown->applied_frames)->toBe([[10, 20, 160, 28]]);
});

it('clamps a wild selection into the options', function () {
    $window = new FakeWindow('main');
    $dropdown = $window->dropdown('planet', ['Mars', 'Venus'], 9, 0, 0, 160, 28);

    expect($dropdown->selectedIndex())->toBe(1);

    $dropdown->select(-3);

    expect($dropdown->selectedIndex())->toBe(0)
        ->and($dropdown->applied_selected)->toBe([0]);
});

it('an empty dropdown answers -1 and null', function () {
    $window = new FakeWindow('main');
    $dropdown = $window->dropdown('planet', [], 0, 0, 0, 160, 28);

    expect($dropdown->selectedIndex())->toBe(-1)
        ->and($dropdown->selectedOption())->toBeNull();
});

it('setOptions replaces the list wholesale and writes through', function () {
    $window = new FakeWindow('main');
    $dropdown = $window->dropdown('planet', ['Mars'], 0, 0, 0, 160, 28);

    $dropdown->setOptions(['Io', 'Titan'], 1);

    expect($dropdown->options())->toBe(['Io', 'Titan'])
        ->and($dropdown->selectedOption())->toBe('Titan')
        ->and($dropdown->applied_options)->toBe([[['Io', 'Titan'], 1]]);
});

it('an engine pick updates the selection, invokes the hook, and rides the dock', function () {
    $dock = bareDock();
    $window = new FakeWindow('main');
    $window->setPool($dock);
    $seen = [];
    $dropdown = $window->dropdown('planet', ['Mars', 'Venus'], 0, 0, 0, 160, 28)
        ->onSelect(function (int $index, ?string $option) use (&$seen) { $seen[] = [$index, $option]; });

    $dropdown->pick(1);

    $mail = $dock->drain()->first(fn (object $mail) => $mail instanceof SelectionChanged);
    expect($dropdown->selectedOption())->toBe('Venus')
        ->and($seen)->toBe([[1, 'Venus']])
        ->and($mail->name)->toBe('main.planet.selected')
        ->and($mail->index)->toBe(1)
        ->and($mail->option)->toBe('Venus');
});
