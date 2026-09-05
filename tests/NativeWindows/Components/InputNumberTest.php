<?php

use Surface\NativeWindows\Components\InputNumber;
use Venusian\Surface\Tests\Support\Fakes\FakeButton;
use Venusian\Surface\Tests\Support\Fakes\FakeTextInput;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('mounts an input and stacked stepper buttons at the component name paths', function () {
    $window = new FakeWindow('main');

    $field = new InputNumber($window, 'qty', 0, 0, 120, 44, value: 3.0);

    expect($window->view('qty.input'))->toBeInstanceOf(FakeTextInput::class)
        ->and($window->view('qty.up'))->toBeInstanceOf(FakeButton::class)
        ->and($window->view('qty.down'))->toBeInstanceOf(FakeButton::class)
        ->and($field->part('input')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 98, 'height' => 44])
        ->and($field->part('up')->frame())->toBe(['x' => 98, 'y' => 0, 'width' => 22, 'height' => 22])
        ->and($field->part('down')->frame())->toBe(['x' => 98, 'y' => 22, 'width' => 22, 'height' => 22])
        ->and($field->value())->toBe(3.0)
        ->and($field->part('input')->value())->toBe('3');
});

it('up and down clicks change the value and fire onChange', function () {
    $window = new FakeWindow('main');
    $field = new InputNumber($window, 'qty', 0, 0, 120, 44, value: 5.0, step: 2.0);
    $seen = [];
    $field->onChange(function (float $value) use (&$seen) { $seen[] = $value; });

    $field->part('up')->click();
    $field->part('down')->click();

    expect($field->value())->toBe(5.0)
        ->and($seen)->toBe([7.0, 5.0])
        ->and($field->part('input')->value())->toBe('5');
});

it('setValue writes the number as the input string and stays silent', function () {
    $window = new FakeWindow('main');
    $field = new InputNumber($window, 'qty', 0, 0, 120, 44);
    $seen = [];
    $field->onChange(function (float $value) use (&$seen) { $seen[] = $value; });

    $field->setValue(8.5);

    expect($seen)->toBe([])
        ->and($field->value())->toBe(8.5)
        ->and($field->part('input')->value())->toBe('8.5');
});

it('button clicks clamp to min and max', function () {
    $window = new FakeWindow('main');
    $field = new InputNumber($window, 'qty', 0, 0, 120, 44, value: 10.0, min: 0.0, max: 10.0);
    $seen = [];
    $field->onChange(function (float $value) use (&$seen) { $seen[] = $value; });

    $field->part('up')->click();
    expect($field->value())->toBe(10.0);

    $field->setValue(0.0);
    $field->part('down')->click();

    expect($field->value())->toBe(0.0)
        ->and($seen)->toBe([10.0, 0.0]);
});

it('numeric typeText updates value and fires; non-numeric typeText does not', function () {
    $window = new FakeWindow('main');
    $field = new InputNumber($window, 'qty', 0, 0, 120, 44, value: 4.0);
    $seen = [];
    $field->onChange(function (float $value) use (&$seen) { $seen[] = $value; });

    /** @var FakeTextInput $input */
    $input = $field->part('input');
    $input->typeText('nope');

    expect($field->value())->toBe(4.0)
        ->and($seen)->toBe([]);

    $input->typeText('3.5');

    expect($field->value())->toBe(3.5)
        ->and($seen)->toBe([3.5]);
});

it('numeric typeText clamps into min and max without snapping the input text', function () {
    $window = new FakeWindow('main');
    $field = new InputNumber($window, 'qty', 0, 0, 120, 44, value: 4.0, min: 0.0, max: 10.0);
    $seen = [];
    $field->onChange(function (float $value) use (&$seen) { $seen[] = $value; });

    /** @var FakeTextInput $input */
    $input = $field->part('input');
    $input->typeText('99');

    expect($field->value())->toBe(10.0)
        ->and($seen)->toBe([10.0])
        ->and($input->value())->toBe('99');
});

it('place stretches the input and keeps the stepper column on the right', function () {
    $window = new FakeWindow('main');
    $field = new InputNumber($window, 'qty', 0, 0, 120, 44);

    $field->place(0, 0, 200, 44);

    expect($field->part('input')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 178, 'height' => 44])
        ->and($field->part('up')->frame())->toBe(['x' => 178, 'y' => 0, 'width' => 22, 'height' => 22])
        ->and($field->part('down')->frame())->toBe(['x' => 178, 'y' => 22, 'width' => 22, 'height' => 22]);
});

it('removal frees the root and part names', function () {
    $window = new FakeWindow('main');
    $field = new InputNumber($window, 'qty', 0, 0, 120, 44);

    $field->remove();

    expect($window->view('qty'))->toBeNull()
        ->and($window->view('qty.input'))->toBeNull()
        ->and($window->view('qty.up'))->toBeNull()
        ->and($window->view('qty.down'))->toBeNull();
});
