<?php

use Surface\NativeWindows\Components\InputText;
use Venusian\Surface\Tests\Support\Fakes\FakeGroup;
use Venusian\Surface\Tests\Support\Fakes\FakeTextInput;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('mounts a text input at the component name path filling the root', function () {
    $window = new FakeWindow('main');

    $field = new InputText($window, 'search', 10, 20, 240, 28, value: 'mars', placeholder: 'Search…');

    expect($window->view('search'))->toBeInstanceOf(FakeGroup::class)
        ->and($window->view('search.input'))->toBeInstanceOf(FakeTextInput::class)
        ->and($field->part('input')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 240, 'height' => 28])
        ->and($field->value())->toBe('mars')
        ->and($field->part('input')->placeholder())->toBe('Search…');
});

it('delegates value reads and writes to the inner input', function () {
    $window = new FakeWindow('main');
    $field = new InputText($window, 'search', 0, 0, 240, 28, value: 'mars');

    $field->setValue('venus');

    expect($field->value())->toBe('venus')
        ->and($field->part('input')->value())->toBe('venus');
});

it('fires onChange and onSubmit from the engine door, not setValue', function () {
    $window = new FakeWindow('main');
    $field = new InputText($window, 'search', 0, 0, 240, 28);
    $seen = [];
    $submitted = null;
    $field->onChange(function (string $value) use (&$seen) { $seen[] = $value; });
    $field->onSubmit(function (string $value) use (&$submitted) { $submitted = $value; });

    $field->setValue('silent');

    expect($seen)->toBe([])
        ->and($field->value())->toBe('silent');

    /** @var FakeTextInput $input */
    $input = $field->part('input');
    $input->typeText('io');
    $input->submit();

    expect($seen)->toBe(['io'])
        ->and($submitted)->toBe('io')
        ->and($field->value())->toBe('io');
});

it('place stretches the inner input to the new inner size', function () {
    $window = new FakeWindow('main');
    $field = new InputText($window, 'search', 0, 0, 240, 28);

    $field->place(0, 0, 400, 32);

    expect($field->part('input')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 400, 'height' => 32]);
});

it('removal frees the root and part names', function () {
    $window = new FakeWindow('main');
    $field = new InputText($window, 'search', 0, 0, 240, 28);

    $field->remove();

    expect($window->view('search'))->toBeNull()
        ->and($window->view('search.input'))->toBeNull()
        ->and($field->part('input'))->toBeNull();
});
