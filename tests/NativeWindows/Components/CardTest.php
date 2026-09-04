<?php

use Surface\NativeWindows\Components\Card;
use Venusian\Surface\Tests\Support\Fakes\FakeGroup;
use Venusian\Surface\Tests\Support\Fakes\FakeLabel;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('mounts a root group and conjures title and body under component names', function () {
    $window = new FakeWindow('main');

    $card = new Card($window, 'weather', 10, 10, 300, 200, title: 'Today');

    expect($window->view('weather'))->toBeInstanceOf(FakeGroup::class)
        ->and($window->view('weather.title'))->toBeInstanceOf(FakeLabel::class)
        ->and($window->view('weather.body'))->toBeInstanceOf(FakeGroup::class)
        ->and($window->view('weather.subtitle'))->toBeNull()
        ->and($card->frame())->toBe(['x' => 10, 'y' => 10, 'width' => 300, 'height' => 200]);
});

it('lays the title at the pad and the body below, component-relative', function () {
    $window = new FakeWindow('main');

    $card = new Card($window, 'weather', 10, 10, 300, 200, title: 'Today', subtitle: 'Cloudy');

    expect($card->part('title')->frame())->toBe(['x' => 12, 'y' => 12, 'width' => 276, 'height' => 22])
        ->and($card->part('subtitle')->frame())->toBe(['x' => 12, 'y' => 34, 'width' => 276, 'height' => 18])
        ->and($card->part('body')->frame())->toBe(['x' => 12, 'y' => 64, 'width' => 276, 'height' => 124]);
});

it('the sketch conjures its own content into the body group', function () {
    $window = new FakeWindow('main');
    $card = new Card($window, 'weather', 0, 0, 300, 200, title: 'Today');

    $label = $card->body()->label('temp', '72°', 0, 0, 100, 30);

    expect($window->view('temp'))->toBe($label)
        ->and($label->hostedBy())->toBe($card->part('body'));
});

it('writes title changes through and grows a subtitle on demand', function () {
    $window = new FakeWindow('main');
    $card = new Card($window, 'weather', 0, 0, 300, 200, title: 'Today');

    $card->setTitle('Tonight');
    $card->setSubtitle('Clear');

    expect($card->part('title')->text())->toBe('Tonight')
        ->and($window->view('weather.subtitle'))->not->toBeNull()
        ->and($card->part('body')->frame()['y'])->toBe(64);
});

it('place re-frames and re-arranges the parts', function () {
    $window = new FakeWindow('main');
    $card = new Card($window, 'weather', 0, 0, 300, 200, title: 'Today');

    $card->place(20, 20, 400, 300);

    expect($card->frame())->toBe(['x' => 20, 'y' => 20, 'width' => 400, 'height' => 300])
        ->and($card->part('title')->frame()['width'])->toBe(376)
        ->and($card->part('body')->frame()['height'])->toBe(242);
});

it('move addresses a part component-relative and keeps its size', function () {
    $window = new FakeWindow('main');
    $card = new Card($window, 'weather', 50, 50, 300, 200, title: 'Today');

    $card->move('title', 30, 40);

    expect($card->part('title')->frame())->toBe(['x' => 30, 'y' => 40, 'width' => 276, 'height' => 22]);
});

it('refuses to move a part it does not have', function () {
    $window = new FakeWindow('main');
    $card = new Card($window, 'weather', 0, 0, 300, 200, title: 'Today');

    expect(fn () => $card->move('ghost', 0, 0))
        ->toThrow(Surface\Contracts\NativeWindows\WindowableException::class, "no part 'ghost'");
});

it('removal is terminal for the whole subtree and frees every name', function () {
    $window = new FakeWindow('main');
    $card = new Card($window, 'weather', 0, 0, 300, 200, title: 'Today');
    $card->body()->label('temp', '72°', 0, 0, 100, 30);

    $card->remove();

    expect($window->view('weather'))->toBeNull()
        ->and($window->view('weather.title'))->toBeNull()
        ->and($window->view('weather.body'))->toBeNull()
        ->and($window->view('temp'))->toBeNull()
        ->and($card->part('title'))->toBeNull();
});
