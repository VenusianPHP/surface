<?php

use Venusian\Surface\Tests\Support\Fakes\FakeScrollView;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('conjures a scroll view whose extent tracks the frame until configured', function () {
    $window = new FakeWindow('main');

    $scroll = $window->scrollView('sidebar', 0, 0, 200, 400);

    expect($scroll)->toBeInstanceOf(FakeScrollView::class)
        ->and($scroll->contentExtent())->toBe([200, 400])
        ->and($scroll->innerSize())->toBe([200, 400]);
});

it('setContentSize writes the extent through and innerSize answers it', function () {
    $window = new FakeWindow('main');
    $scroll = $window->scrollView('sidebar', 0, 0, 200, 400);

    $scroll->setContentSize(200, 1200);

    expect($scroll->contentExtent())->toBe([200, 1200])
        ->and($scroll->innerSize())->toBe([200, 1200])
        ->and($scroll->applied_content_sizes)->toBe([[200, 1200]]);
});

it('children lay out against the extent, not the viewport', function () {
    $window = new FakeWindow('main');
    $scroll = $window->scrollView('sidebar', 0, 0, 200, 400);
    $scroll->setContentSize(200, 1200);

    $label = $scroll->label('row_40', 'Row 40', 0, 1150, 200, 30);
    $centered = $scroll->button('go', 'Go', 0, 0, 1, 1)->hug()->center();

    // 1150 is far below the 400px viewport — legal, it scrolls to it.
    expect($label->frame()['y'])->toBe(1150)
        // Centered against 200x1200: natural 80x30 → (60, 585).
        ->and($centered->frame())->toBe(['x' => 60, 'y' => 585, 'width' => 80, 'height' => 30]);
});

it('growing the extent re-resolves the children already inside', function () {
    $window = new FakeWindow('main');
    $scroll = $window->scrollView('sidebar', 0, 0, 200, 400);
    $centered = $scroll->button('go', 'Go', 0, 0, 1, 1)->hug()->center();

    $scroll->setContentSize(200, 1200);

    expect($centered->frame()['y'])->toBe(585);
});

it('an unconfigured scroll view behaves like a plain group', function () {
    $window = new FakeWindow('main');
    $scroll = $window->scrollView('sidebar', 0, 0, 200, 400);

    $centered = $scroll->button('go', 'Go', 0, 0, 1, 1)->hug()->center();

    expect($centered->frame())->toBe(['x' => 60, 'y' => 185, 'width' => 80, 'height' => 30]);
});
