<?php

use Surface\Contracts\NativeWindows\Views\FontWeight;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

/*
|--------------------------------------------------------------------------
| Wrapped labels
|--------------------------------------------------------------------------
|
| wrap($width) is the third sizing rule: the width is fixed by the rule,
| the height is whatever the engine measures for the text flowed at that
| width. Anything that changes the flow — text, font — re-measures, the
| same way NATURAL views re-measure.
|
*/

it('wrap fixes the width and takes the wrapped height from the engine', function () {
    $window = new FakeWindow('main');
    $label = $window->label('body', 'A long explanation…', 10, 20, 1, 1);
    $label->wrapped_height = 96;

    $label->wrap(300);

    expect($label->applied_wraps)->toBe([300])
        ->and($label->measured_wrap_widths)->toBe([300])
        ->and($label->frame())->toBe(['x' => 10, 'y' => 20, 'width' => 300, 'height' => 96]);
});

it('re-measures the wrapped height when the text changes', function () {
    $window = new FakeWindow('main');
    $label = $window->label('body', 'Short.', 0, 0, 1, 1);
    $label->wrap(300);
    $label->wrapped_height = 240;

    $label->setText('A far longer explanation that flows onto many more lines.');

    expect($label->frame()['height'])->toBe(240)
        ->and($label->frame()['width'])->toBe(300);
});

it('re-measures the wrapped height when the font changes', function () {
    $window = new FakeWindow('main');
    $label = $window->label('body', 'Some text', 0, 0, 1, 1);
    $label->wrap(300);
    $label->wrapped_height = 180;

    $label->setFont(24.0, FontWeight::BOLD);

    expect($label->frame()['height'])->toBe(180);
});

it('a centred wrapped label re-resolves against the content size', function () {
    $window = new FakeWindow('main');
    $window->content_size = [640, 480];
    $label = $window->label('body', 'Some text', 0, 0, 1, 1);
    $label->wrapped_height = 100;

    $label->wrap(300)->center();

    expect($label->frame())->toBe(['x' => 170, 'y' => 190, 'width' => 300, 'height' => 100]);
});
