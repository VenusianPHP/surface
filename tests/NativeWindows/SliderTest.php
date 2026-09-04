<?php

use Surface\Contracts\NativeWindows\Events\View\ValueChanged;
use Venusian\Surface\Tests\Support\Fakes\FakeSlider;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('conjures a slider with range and value, and places it at once', function () {
    $window = new FakeWindow('main');

    $slider = $window->slider('volume', 0.0, 1.0, 0.5, 10, 20, 300, 28);

    expect($slider)->toBeInstanceOf(FakeSlider::class)
        ->and($slider->min())->toBe(0.0)
        ->and($slider->max())->toBe(1.0)
        ->and($slider->value())->toBe(0.5)
        ->and($slider->applied_frames)->toBe([[10, 20, 300, 28]]);
});

it('clamps writes into the range', function () {
    $window = new FakeWindow('main');
    $slider = $window->slider('volume', 0.0, 1.0, 0.5, 0, 0, 300, 28);

    $slider->setValue(7.5);

    expect($slider->value())->toBe(1.0)
        ->and($slider->applied_values)->toBe([1.0]);
});

it('setRange re-clamps the held value against the new range', function () {
    $window = new FakeWindow('main');
    $slider = $window->slider('volume', 0.0, 10.0, 8.0, 0, 0, 300, 28);

    $slider->setRange(0.0, 5.0);

    expect($slider->min())->toBe(0.0)
        ->and($slider->max())->toBe(5.0)
        ->and($slider->value())->toBe(5.0)
        ->and($slider->applied_ranges)->toBe([[0.0, 5.0]]);
});

it('an engine drag updates the value, invokes the hook, and rides the dock', function () {
    $dock = bareDock();
    $window = new FakeWindow('main');
    $window->setPool($dock);
    $seen = [];
    $slider = $window->slider('volume', 0.0, 1.0, 0.0, 0, 0, 300, 28)
        ->onChange(function (float $value) use (&$seen) { $seen[] = $value; });

    $slider->drag(0.25);

    $mail = $dock->drain()->first(fn (object $mail) => $mail instanceof ValueChanged);
    expect($slider->value())->toBe(0.25)
        ->and($seen)->toBe([0.25])
        ->and($mail->name)->toBe('main.volume.changed')
        ->and($mail->value)->toBe(0.25);
});

it('dragging with no pool and no hook is still safe', function () {
    $window = new FakeWindow('main');
    $window->slider('volume', 0.0, 1.0, 0.0, 0, 0, 300, 28)->drag(0.5);

    expect(true)->toBeTrue();
});
