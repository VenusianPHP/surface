<?php

use Surface\Contracts\NativeWindows\WindowableException;
use Surface\NativeWindows\Components\ListBox;
use Venusian\Surface\Tests\Support\Fakes\FakeScrollView;
use Venusian\Surface\Tests\Support\Fakes\FakeToggleButton;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

function listBoxUnderTest(FakeWindow $window, int $width = 200, int $height = 400): ListBox
{
    $list = new ListBox($window, 'list', 0, 0, $width, $height);
    $list->addOption('home', 'Home');
    $list->addOption('stars', 'Stargazer');
    $list->addOption('weather', 'Weather');

    return $list;
}

it('mounts a scroll region and stacks option rows inside it', function () {
    $window = new FakeWindow('main');
    $list = listBoxUnderTest($window);

    /** @var FakeScrollView $scroll */
    $scroll = $window->view('list.scroll');
    /** @var FakeToggleButton $home */
    $home = $window->view('list.item.home');

    expect($scroll)->toBeInstanceOf(FakeScrollView::class)
        ->and($home)->toBeInstanceOf(FakeToggleButton::class)
        ->and($home->hostedBy())->toBe($scroll)
        ->and($home->frame())->toBe(['x' => 8, 'y' => 8, 'width' => 184, 'height' => 36])
        ->and($window->view('list.item.stars')->frame()['y'])->toBe(48)
        ->and($window->view('list.item.weather')->frame()['y'])->toBe(88);
});

it('the extent covers every row and never shrinks under the viewport', function () {
    $window = new FakeWindow('main');
    $list = listBoxUnderTest($window);

    /** @var FakeScrollView $scroll */
    $scroll = $list->part('scroll');

    // 3 rows: 8 + 3*36 + 2*4 + 8 = 132 — under the 400 viewport, so 400.
    expect($scroll->contentExtent())->toBe([200, 400]);

    for ($i = 0; $i < 10; $i++) {
        $list->addOption("extra{$i}", "Extra {$i}");
    }

    // 13 rows: 8 + 13*36 + 12*4 + 8 = 532.
    expect($scroll->contentExtent())->toBe([200, 532]);
});

it('selection is exclusive: one pressed row once anything is selected', function () {
    $window = new FakeWindow('main');
    $list = listBoxUnderTest($window);

    $list->select('stars');
    $list->select('weather');

    expect($list->selectedKey())->toBe('weather')
        ->and($window->view('list.item.weather')->isPressed())->toBeTrue()
        ->and($window->view('list.item.stars')->isPressed())->toBeFalse()
        ->and($window->view('list.item.home')->isPressed())->toBeFalse();
});

it('a user press selects, runs the hook, and releases the previous row', function () {
    $window = new FakeWindow('main');
    $list = listBoxUnderTest($window);
    $picked = [];
    $list->onSelect(function (string $key) use (&$picked) { $picked[] = $key; });
    $list->select('home');

    /** @var FakeToggleButton $stars */
    $stars = $window->view('list.item.stars');
    $stars->press(true);

    expect($list->selectedKey())->toBe('stars')
        ->and($picked)->toBe(['stars'])
        ->and($window->view('list.item.home')->isPressed())->toBeFalse();
});

it('programmatic select stays silent', function () {
    $window = new FakeWindow('main');
    $list = listBoxUnderTest($window);
    $picked = [];
    $list->onSelect(function (string $key) use (&$picked) { $picked[] = $key; });

    $list->select('home');

    expect($picked)->toBe([]);
});

it('unpressing the selected row snaps it back — selection is sticky', function () {
    $window = new FakeWindow('main');
    $list = listBoxUnderTest($window);
    $list->select('home');

    /** @var FakeToggleButton $home */
    $home = $window->view('list.item.home');
    $home->press(false);

    expect($home->isPressed())->toBeTrue()
        ->and($list->selectedKey())->toBe('home');
});

it('refuses a duplicate option key and selecting a ghost', function () {
    $window = new FakeWindow('main');
    $list = listBoxUnderTest($window);

    expect(fn () => $list->addOption('home', 'Again'))
        ->toThrow(WindowableException::class, "already has an option 'home'")
        ->and(fn () => $list->select('ghost'))
        ->toThrow(WindowableException::class, "has no option 'ghost'");
});

it('place re-frames the scroll and stretches the rows', function () {
    $window = new FakeWindow('main');
    $list = listBoxUnderTest($window);

    $list->place(0, 0, 160, 300);

    expect($list->part('scroll')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 160, 'height' => 300])
        ->and($list->part('item.home')->frame()['width'])->toBe(144);
});

it('removal is terminal for the scroll, rows and root', function () {
    $window = new FakeWindow('main');
    $list = listBoxUnderTest($window);

    $list->remove();

    expect($window->view('list'))->toBeNull()
        ->and($window->view('list.scroll'))->toBeNull()
        ->and($window->view('list.item.home'))->toBeNull()
        ->and($window->view('list.item.weather'))->toBeNull();
});
