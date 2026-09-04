<?php

use Surface\NativeWindows\Components\Card;
use Venusian\Surface\Tests\Support\Fakes\FakeGroup;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('starts visible and writes through only on change', function () {
    $window = new FakeWindow('main');
    $button = $window->button('go', 'Go', 0, 0, 80, 30);

    expect($button->isVisible())->toBeTrue()
        ->and($button->applied_visible)->toBe([]);

    $button->hide();
    $button->hide();
    $button->show();

    expect($button->isVisible())->toBeTrue()
        ->and($button->applied_visible)->toBe([false, true]);
});

it('a hidden view keeps its frame and rules', function () {
    $window = new FakeWindow('main');
    $button = $window->button('go', 'Go', 10, 20, 80, 30);

    $button->hide();

    expect($button->frame())->toBe(['x' => 10, 'y' => 20, 'width' => 80, 'height' => 30]);
});

it('hiding a group is one native write — the engine takes the subtree', function () {
    $window = new FakeWindow('main');
    /** @var FakeGroup $group */
    $group = $window->group('panel', 0, 0, 200, 200);
    $child = $group->button('go', 'Go', 0, 0, 80, 30);

    $group->hide();

    expect($group->applied_visible)->toBe([false])
        ->and($child->applied_visible)->toBe([])
        ->and($child->isVisible())->toBeTrue();
});

it('a component shows and hides through its root', function () {
    $window = new FakeWindow('main');
    $card = new Card($window, 'weather', 0, 0, 300, 200, title: 'Today');

    $card->hide();

    /** @var FakeGroup $root */
    $root = $window->view('weather');
    expect($card->isVisible())->toBeFalse()
        ->and($root->applied_visible)->toBe([false]);

    $card->show();

    expect($card->isVisible())->toBeTrue();
});
