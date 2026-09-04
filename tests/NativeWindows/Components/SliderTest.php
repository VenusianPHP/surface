<?php

use Surface\NativeWindows\Components\Slider;
use Venusian\Surface\Tests\Support\Fakes\FakeGroup;
use Venusian\Surface\Tests\Support\Fakes\FakeSlider;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('mounts a slider at the component name path filling the root', function () {
    $window = new FakeWindow('main');

    $slider = new Slider($window, 'volume', 10, 20, 200, 24, min: 0.0, max: 100.0, value: 40.0);

    expect($window->view('volume'))->toBeInstanceOf(FakeGroup::class)
        ->and($window->view('volume.slider'))->toBeInstanceOf(FakeSlider::class)
        ->and($slider->part('slider')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 200, 'height' => 24])
        ->and($slider->value())->toBe(40.0)
        ->and($slider->min())->toBe(0.0)
        ->and($slider->max())->toBe(100.0);
});

it('delegates value and range reads and writes to the inner slider', function () {
    $window = new FakeWindow('main');
    $slider = new Slider($window, 'volume', 0, 0, 200, 24, min: 0.0, max: 100.0, value: 40.0);

    $slider->setValue(25.0);
    $slider->setRange(10.0, 50.0);

    expect($slider->value())->toBe(25.0)
        ->and($slider->min())->toBe(10.0)
        ->and($slider->max())->toBe(50.0)
        ->and($slider->part('slider')->value())->toBe(25.0)
        ->and($slider->part('slider')->min())->toBe(10.0)
        ->and($slider->part('slider')->max())->toBe(50.0);
});

it('fires onChange from the engine door, not setValue', function () {
    $window = new FakeWindow('main');
    $slider = new Slider($window, 'volume', 0, 0, 200, 24, min: 0.0, max: 100.0, value: 40.0);
    $seen = [];
    $slider->onChange(function (float $value) use (&$seen) { $seen[] = $value; });

    $slider->setValue(20.0);

    expect($seen)->toBe([])
        ->and($slider->value())->toBe(20.0);

    /** @var FakeSlider $inner */
    $inner = $slider->part('slider');
    $inner->drag(30.0);

    expect($seen)->toBe([30.0])
        ->and($slider->value())->toBe(30.0);
});

it('place stretches the inner slider to the new inner size', function () {
    $window = new FakeWindow('main');
    $slider = new Slider($window, 'volume', 0, 0, 200, 24, min: 0.0, max: 100.0, value: 40.0);

    $slider->place(0, 0, 360, 28);

    expect($slider->part('slider')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 360, 'height' => 28]);
});

it('removal frees the root and part names', function () {
    $window = new FakeWindow('main');
    $slider = new Slider($window, 'volume', 0, 0, 200, 24, min: 0.0, max: 100.0, value: 40.0);

    $slider->remove();

    expect($window->view('volume'))->toBeNull()
        ->and($window->view('volume.slider'))->toBeNull()
        ->and($slider->part('slider'))->toBeNull();
});
