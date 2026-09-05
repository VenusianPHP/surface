<?php

use Surface\NativeWindows\Components\Chip;
use Venusian\Surface\Tests\Support\Fakes\FakeGroup;
use Venusian\Surface\Tests\Support\Fakes\FakeLabel;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('mounts a label at the component name path', function () {
    $window = new FakeWindow('main');

    $chip = new Chip($window, 'filter', 0, 0, 300, 40, label: 'Mars');

    expect($window->view('filter'))->toBeInstanceOf(FakeGroup::class)
        ->and($window->view('filter.label'))->toBeInstanceOf(FakeLabel::class)
        ->and($window->view('filter.close'))->toBeNull()
        ->and($chip->part('label')->text())->toBe('Mars');
});

it('a removable chip dismisses itself on the close click and then hooks', function () {
    $window = new FakeWindow('main');
    $order = [];
    $chip = new Chip($window, 'filter', 0, 0, 300, 40, label: 'Bye', removable: true);
    $chip->onRemove(function () use (&$order, $window) {
        $order[] = is_null($window->view('filter')) ? 'gone-first' : 'still-there';
    });

    $close = $chip->part('close');
    expect($close)->not->toBeNull()
        ->and($close->frame())->toBe(['x' => 270, 'y' => 9, 'width' => 22, 'height' => 22]);

    $close->click();

    expect($order)->toBe(['gone-first'])
        ->and($window->view('filter'))->toBeNull()
        ->and($window->view('filter.label'))->toBeNull()
        ->and($window->view('filter.close'))->toBeNull();
});

it('the label makes room for the close button only when removable', function () {
    $window = new FakeWindow('main');
    $plain = new Chip($window, 'a', 0, 0, 300, 40, label: 'x');
    $removable = new Chip($window, 'b', 0, 0, 300, 40, label: 'x', removable: true);

    expect($plain->part('label')->frame()['width'])->toBe(284)
        ->and($removable->part('label')->frame()['width'])->toBe(254);
});

it('place re-arranges the label and close against the new inner size', function () {
    $window = new FakeWindow('main');
    $chip = new Chip($window, 'filter', 0, 0, 300, 40, label: 'Mars', removable: true);

    $chip->place(0, 0, 400, 48);

    expect($chip->part('label')->frame())->toBe(['x' => 8, 'y' => 15, 'width' => 354, 'height' => 18])
        ->and($chip->part('close')->frame())->toBe(['x' => 370, 'y' => 13, 'width' => 22, 'height' => 22]);
});

it('removal frees the root and part names', function () {
    $window = new FakeWindow('main');
    $chip = new Chip($window, 'filter', 0, 0, 300, 40, label: 'Mars', removable: true);

    $chip->remove();

    expect($window->view('filter'))->toBeNull()
        ->and($window->view('filter.label'))->toBeNull()
        ->and($window->view('filter.close'))->toBeNull();
});
