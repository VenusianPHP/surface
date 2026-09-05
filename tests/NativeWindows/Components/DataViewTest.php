<?php

use Surface\NativeWindows\Components\DataView;
use Venusian\Surface\Tests\Support\Fakes\FakeGroup;
use Venusian\Surface\Tests\Support\Fakes\FakeScrollView;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('stacks sketch-filled slots inside a scroll region', function () {
    $window = new FakeWindow('main');
    $feed = new DataView($window, 'feed', 0, 0, 200, 100);

    $first = $feed->addItem('a');
    $second = $feed->addItem('b');

    expect($window->view('feed.scroll'))->toBeInstanceOf(FakeScrollView::class)
        ->and($first)->toBeInstanceOf(FakeGroup::class)
        ->and($second)->toBe($window->view('feed.item.b'))
        ->and($first->hostedBy())->toBe($feed->part('scroll'))
        ->and($feed->part('item.a')->frame())->toBe(['x' => 8, 'y' => 8, 'width' => 184, 'height' => 72])
        ->and($feed->part('item.b')->frame())->toBe(['x' => 8, 'y' => 88, 'width' => 184, 'height' => 72]);
});

it('the sketch conjures content into a slot and the extent covers both items', function () {
    $window = new FakeWindow('main');
    $feed = new DataView($window, 'feed', 0, 0, 200, 100);

    $slot = $feed->addItem('a');
    $feed->addItem('b');
    $label = $slot->label('row-a-title', 'Hello', 0, 0, 80, 20);

    /** @var FakeScrollView $scroll */
    $scroll = $feed->part('scroll');

    expect($window->view('row-a-title'))->toBe($label)
        ->and($label->hostedBy())->toBe($feed->part('item.a'))
        // pad 8 + 72 + gap 8 + 72 + pad 8 = 168, taller than the 100 viewport.
        ->and($scroll->contentExtent())->toBe([200, 168]);
});

it('place re-frames the scroll and stretches the slots', function () {
    $window = new FakeWindow('main');
    $feed = new DataView($window, 'feed', 0, 0, 200, 100);
    $feed->addItem('a');
    $feed->addItem('b', 40);

    $feed->place(0, 0, 300, 160);

    expect($feed->part('scroll')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 300, 'height' => 160])
        ->and($feed->part('item.a')->frame())->toBe(['x' => 8, 'y' => 8, 'width' => 284, 'height' => 72])
        ->and($feed->part('item.b')->frame())->toBe(['x' => 8, 'y' => 88, 'width' => 284, 'height' => 40]);
});

it('removal frees the scroll and item names', function () {
    $window = new FakeWindow('main');
    $feed = new DataView($window, 'feed', 0, 0, 200, 200);
    $feed->addItem('a')->label('row-a-title', 'Hello', 0, 0, 80, 20);
    $feed->addItem('b');

    $feed->remove();

    expect($window->view('feed'))->toBeNull()
        ->and($window->view('feed.scroll'))->toBeNull()
        ->and($window->view('feed.item.a'))->toBeNull()
        ->and($window->view('feed.item.b'))->toBeNull()
        ->and($window->view('row-a-title'))->toBeNull();
});
