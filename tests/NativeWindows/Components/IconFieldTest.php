<?php

use Surface\NativeWindows\Components\IconField;
use Venusian\Surface\Tests\Support\Fakes\FakeTextInput;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('mounts an icon glyph beside a text input, input filling the rest', function () {
    $window = new FakeWindow('main');

    $field = new IconField($window, 'search', 0, 0, 240, 28, icon: '🔍', placeholder: 'Search…');

    expect($window->view('search.icon'))->not->toBeNull()
        ->and($window->view('search.input'))->toBeInstanceOf(FakeTextInput::class)
        ->and($field->part('icon')->frame())->toBe(['x' => 0, 'y' => 3, 'width' => 22, 'height' => 22])
        ->and($field->part('input')->frame())->toBe(['x' => 28, 'y' => 0, 'width' => 212, 'height' => 28])
        ->and($field->input()->placeholder())->toBe('Search…');
});

it('delegates value reads and writes to the inner input', function () {
    $window = new FakeWindow('main');
    $field = new IconField($window, 'search', 0, 0, 240, 28, icon: '🔍', value: 'mars');

    $field->setValue('venus');

    expect($field->value())->toBe('venus')
        ->and($field->input()->value())->toBe('venus');
});

it('delegates the hooks, which see engine edits', function () {
    $window = new FakeWindow('main');
    $field = new IconField($window, 'search', 0, 0, 240, 28, icon: '🔍');
    $seen = [];
    $submitted = null;
    $field->onChange(function (string $value) use (&$seen) { $seen[] = $value; });
    $field->onSubmit(function (string $value) use (&$submitted) { $submitted = $value; });

    /** @var FakeTextInput $input */
    $input = $field->part('input');
    $input->typeText('io');
    $input->submit();

    expect($seen)->toBe(['io'])
        ->and($submitted)->toBe('io')
        ->and($field->value())->toBe('io');
});

it('re-placing keeps the icon square and stretches the input', function () {
    $window = new FakeWindow('main');
    $field = new IconField($window, 'search', 0, 0, 240, 28, icon: '🔍');

    $field->place(0, 0, 400, 32);

    expect($field->part('input')->frame())->toBe(['x' => 28, 'y' => 0, 'width' => 372, 'height' => 32]);
});
