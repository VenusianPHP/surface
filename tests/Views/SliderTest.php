<?php

use Surface\Contracts\NativeWindows\Views\SliderHandle;
use Surface\Contracts\NativeWindows\Views\ViewException;
use Surface\NativeWindows\Enums\ViewType;
use Surface\NativeWindows\Views\Frame;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeBox;
use Venusian\Surface\Tests\Views\Fakes\FakeSlider;

function sliderUnderTest(CallLog $log): FakeSlider
{
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $slider = $root->slider('volume', 0.0, 1.0, 0.5, 20, 20, 300, 28);
    $log->clear();

    return $slider;
}

it('conjures a slider with range and value', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));

    $slider = $root->slider('volume', 0.0, 1.0, 0.5, 20, 20, 300, 28);

    expect($slider)->toBeInstanceOf(SliderHandle::class)
        ->and($slider->type())->toBe(ViewType::SLIDER)
        ->and($slider->min())->toBe(0.0)
        ->and($slider->max())->toBe(1.0)
        ->and($log->ops())->toBe(['createSlider', 'attach', 'setFrame'])
        ->and($log->entries[0]['args'])->toBe([0.0, 1.0, 0.5]);
});

it('refuses an empty or inverted range at birth', function () {
    $root = new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 640, 480));

    $root->slider('volume', 5.0, 1.0, 2.0, 20, 20, 300, 28);
})->throws(ViewException::class, '5');

it('re-ranges natively and remembers it', function () {
    $log = new CallLog;
    $slider = sliderUnderTest($log);

    $slider->range(10.0, 20.0);

    expect($slider->min())->toBe(10.0)
        ->and($slider->max())->toBe(20.0)
        ->and($log->entries[0])->toBe(['op' => 'setRange', 'pointer' => $slider->pointer(), 'args' => [10.0, 20.0]]);
});

it('refuses an inverted range later too', function () {
    sliderUnderTest(new CallLog)->range(3.0, 3.0);
})->throws(ViewException::class);

it('pushes and pulls its value', function () {
    $log = new CallLog;
    $slider = sliderUnderTest($log);

    $slider->setValue(0.25);

    expect($log->entries[0]['args'])->toBe([0.25])
        ->and($slider->value())->toBe(0.25)
        ->and($log->ops())->toBe(['setValue', 'getValue']);
});

it('routes change through a trampoline', function () {
    $log = new CallLog;
    $slider = sliderUnderTest($log);
    $received = null;

    $slider->onChange(function (SliderHandle $s) use (&$received): void { $received = $s; });
    ($log->entries[0]['args'][0])();

    expect($received)->toBe($slider);
});
