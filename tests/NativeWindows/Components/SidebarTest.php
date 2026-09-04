<?php

use Surface\Contracts\NativeWindows\WindowableException;
use Surface\NativeWindows\Components\Sidebar;
use Venusian\Surface\Tests\Support\Fakes\FakeScrollView;
use Venusian\Surface\Tests\Support\Fakes\FakeToggleButton;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

function sidebarUnderTest(FakeWindow $window, int $width = 200, int $height = 400): Sidebar
{
    $sidebar = new Sidebar($window, 'nav', 0, 0, $width, $height);
    $sidebar->addLink('home', 'Home', '🏠');
    $sidebar->addLink('stars', 'Stargazer', '✨');
    $sidebar->addLink('weather', 'Weather');

    return $sidebar;
}

it('mounts a scroll region and stacks link rows inside it', function () {
    $window = new FakeWindow('main');
    $sidebar = sidebarUnderTest($window);

    /** @var FakeScrollView $scroll */
    $scroll = $window->view('nav.scroll');
    /** @var FakeToggleButton $home */
    $home = $window->view('nav.link.home');

    expect($scroll)->toBeInstanceOf(FakeScrollView::class)
        ->and($home)->toBeInstanceOf(FakeToggleButton::class)
        ->and($home->hostedBy())->toBe($scroll)
        ->and($sidebar->links())->toBe(['home', 'stars', 'weather'])
        // pad 8, rows 36 tall with a 4 gap.
        ->and($home->frame())->toBe(['x' => 8, 'y' => 8, 'width' => 184, 'height' => 36])
        ->and($window->view('nav.link.stars')->frame()['y'])->toBe(48)
        ->and($window->view('nav.link.weather')->frame()['y'])->toBe(88);
});

it('wide rows read glyph plus label; a glyphless link reads its label', function () {
    $window = new FakeWindow('main');
    sidebarUnderTest($window);

    expect($window->view('nav.link.home')->label())->toBe('🏠  Home')
        ->and($window->view('nav.link.weather')->label())->toBe('Weather');
});

it('the extent covers every row and never shrinks under the viewport', function () {
    $window = new FakeWindow('main');
    $sidebar = sidebarUnderTest($window);

    /** @var FakeScrollView $scroll */
    $scroll = $sidebar->part('scroll');

    // 3 rows: 8 + 3*36 + 2*4 + 8 = 132 — under the 400 viewport, so 400.
    expect($scroll->contentExtent())->toBe([200, 400]);

    for ($i = 0; $i < 10; $i++) {
        $sidebar->addLink("extra{$i}", "Extra {$i}");
    }

    // 13 rows: 8 + 13*36 + 12*4 + 8 = 532.
    expect($scroll->contentExtent())->toBe([200, 532]);
});

it('selection is exclusive: one pressed row once anything is selected', function () {
    $window = new FakeWindow('main');
    $sidebar = sidebarUnderTest($window);

    $sidebar->select('stars');
    $sidebar->select('weather');

    expect($sidebar->selectedKey())->toBe('weather')
        ->and($window->view('nav.link.weather')->isPressed())->toBeTrue()
        ->and($window->view('nav.link.stars')->isPressed())->toBeFalse()
        ->and($window->view('nav.link.home')->isPressed())->toBeFalse();
});

it('a user press selects, runs the hook, and releases the previous row', function () {
    $window = new FakeWindow('main');
    $sidebar = sidebarUnderTest($window);
    $picked = [];
    $sidebar->onSelect(function (string $key) use (&$picked) { $picked[] = $key; });
    $sidebar->select('home');

    /** @var FakeToggleButton $stars */
    $stars = $window->view('nav.link.stars');
    $stars->press(true);

    expect($sidebar->selectedKey())->toBe('stars')
        ->and($picked)->toBe(['stars'])
        ->and($window->view('nav.link.home')->isPressed())->toBeFalse();
});

it('programmatic select stays silent', function () {
    $window = new FakeWindow('main');
    $sidebar = sidebarUnderTest($window);
    $picked = [];
    $sidebar->onSelect(function (string $key) use (&$picked) { $picked[] = $key; });

    $sidebar->select('home');

    expect($picked)->toBe([]);
});

it('unpressing the selected row snaps it back — selection is sticky', function () {
    $window = new FakeWindow('main');
    $sidebar = sidebarUnderTest($window);
    $sidebar->select('home');

    /** @var FakeToggleButton $home */
    $home = $window->view('nav.link.home');
    $home->press(false);

    expect($home->isPressed())->toBeTrue()
        ->and($sidebar->selectedKey())->toBe('home');
});

it('placed under the breakpoint the rows snap to their glyphs', function () {
    $window = new FakeWindow('main');
    $sidebar = sidebarUnderTest($window);

    $sidebar->place(0, 0, 60, 400);

    expect($sidebar->collapsed())->toBeTrue()
        ->and($window->view('nav.link.home')->label())->toBe('🏠')
        ->and($window->view('nav.link.weather')->label())->toBe('W')
        ->and($window->view('nav.link.home')->frame()['width'])->toBe(44);

    $sidebar->place(0, 0, 200, 400);

    expect($sidebar->collapsed())->toBeFalse()
        ->and($window->view('nav.link.home')->label())->toBe('🏠  Home');
});

it('refuses a duplicate link key and selecting a ghost', function () {
    $window = new FakeWindow('main');
    $sidebar = sidebarUnderTest($window);

    expect(fn () => $sidebar->addLink('home', 'Again'))
        ->toThrow(WindowableException::class, "already has a link 'home'")
        ->and(fn () => $sidebar->select('ghost'))
        ->toThrow(WindowableException::class, "no link 'ghost'");
});

it('removal is terminal for the scroll, rows and root', function () {
    $window = new FakeWindow('main');
    $sidebar = sidebarUnderTest($window);

    $sidebar->remove();

    expect($window->view('nav'))->toBeNull()
        ->and($window->view('nav.scroll'))->toBeNull()
        ->and($window->view('nav.link.home'))->toBeNull()
        ->and($window->view('nav.link.weather'))->toBeNull();
});
