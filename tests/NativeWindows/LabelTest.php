<?php

use Surface\Contracts\NativeWindows\Views\TextAlignment;
use Surface\Contracts\NativeWindows\WindowableException;
use Venusian\Surface\Tests\Support\Fakes\FakeLabel;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

/*
|--------------------------------------------------------------------------
| Conjuring a label
|--------------------------------------------------------------------------
|
| Windowable owns the name registry; View owns the frame Surface believes
| in and the centre/hug arithmetic in top-left pixels. Engines only receive
| already-decided frames through applyFrame(), so all of this is provable
| with a fake that records.
|
*/

it('conjures a label, registers it by name and places it at once', function () {
    $window = new FakeWindow('main');

    $label = $window->label('title', 'Hello', 10, 20, 100, 30);

    expect($label)->toBeInstanceOf(FakeLabel::class)
        ->and($label->name())->toBe('title')
        ->and($label->text())->toBe('Hello')
        ->and($window->view('title'))->toBe($label)
        ->and($label->frame())->toBe(['x' => 10, 'y' => 20, 'width' => 100, 'height' => 30])
        ->and($label->applied_frames)->toBe([[10, 20, 100, 30]]);
});

it('refuses a second view under a taken name', function () {
    $window = new FakeWindow('main');
    $window->label('title', 'Hello', 0, 0, 1, 1);

    expect(fn () => $window->label('title', 'Again', 0, 0, 1, 1))
        ->toThrow(WindowableException::class, "View 'title' already exists");
});

it('answers null for a view it does not hold', function () {
    expect((new FakeWindow('main'))->view('ghost'))->toBeNull();
});

it('hugs to the natural size and keeps the origin', function () {
    $window = new FakeWindow('main');
    $label = $window->label('title', 'Hello', 10, 20, 1, 1);
    $label->natural_size = [120, 24];

    $label->hug();

    expect($label->frame())->toBe(['x' => 10, 'y' => 20, 'width' => 120, 'height' => 24]);
});

it('centres the current frame inside the content', function () {
    $window = new FakeWindow('main');
    $window->content_size = [640, 480];
    $label = $window->label('title', 'Hello', 0, 0, 100, 20);

    $label->center();

    expect($label->frame())->toBe(['x' => 270, 'y' => 230, 'width' => 100, 'height' => 20]);
});

it('hug then center lands the natural size in the middle, and chains', function () {
    $window = new FakeWindow('main');
    $window->content_size = [400, 600];
    $label = $window->label('title', 'Hello World!', 0, 0, 1, 1);
    $label->natural_size = [140, 28];

    $result = $label->align(TextAlignment::CENTER)->hug()->center();

    expect($result)->toBe($label)
        ->and($label->frame())->toBe(['x' => 130, 'y' => 286, 'width' => 140, 'height' => 28])
        ->and($label->applied_alignments)->toBe([TextAlignment::CENTER]);
});

it('centres by flooring so odd remainders never land on a half pixel', function () {
    $window = new FakeWindow('main');
    $window->content_size = [401, 601];
    $label = $window->label('title', 'x', 0, 0, 100, 20);

    $label->center();

    expect($label->frame()['x'])->toBe(150)
        ->and($label->frame()['y'])->toBe(290);
});

it('writes text through to the engine and remembers it', function () {
    $window = new FakeWindow('main');
    $label = $window->label('title', 'Hello', 0, 0, 1, 1);

    $label->setText('Bye');

    expect($label->text())->toBe('Bye')
        ->and($label->applied_texts)->toBe(['Bye']);
});

it('removal is terminal: destroys the native node and frees the name', function () {
    $window = new FakeWindow('main');
    $label = $window->label('title', 'Hello', 0, 0, 1, 1);

    $label->remove();

    expect($label->destroyed)->toBeTrue()
        ->and($window->view('title'))->toBeNull();

    $window->label('title', 'Reused', 0, 0, 1, 1);
    expect($window->view('title')->text())->toBe('Reused');
});
