<?php

use Surface\Contracts\NativeWindows\WindowableException;
use Surface\NativeWindows\Components\Tabs;
use Venusian\Surface\Tests\Support\Fakes\FakeGroup;
use Venusian\Surface\Tests\Support\Fakes\FakeToggleButton;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

function tabsUnderTest(FakeWindow $window, int $width = 300, int $height = 200): Tabs
{
    $tabs = new Tabs($window, 'pages', 0, 0, $width, $height);
    $tabs->addTab('a', 'Alpha');
    $tabs->addTab('b', 'Beta');

    return $tabs;
}

it('mounts headers and panels; only the first panel is visible', function () {
    $window = new FakeWindow('main');
    $tabs = tabsUnderTest($window);

    expect($window->view('pages.tab.a'))->toBeInstanceOf(FakeToggleButton::class)
        ->and($window->view('pages.panel.a'))->toBeInstanceOf(FakeGroup::class)
        ->and($window->view('pages.panel.b'))->toBeInstanceOf(FakeGroup::class)
        ->and($tabs->selectedKey())->toBe('a')
        ->and($window->view('pages.panel.a')->isVisible())->toBeTrue()
        ->and($window->view('pages.panel.b')->isVisible())->toBeFalse()
        ->and($window->view('pages.tab.a')->isPressed())->toBeTrue()
        ->and($window->view('pages.tab.b')->isPressed())->toBeFalse();
});

it('flows hugged headers in a 32-tall strip and stacks panels in the remaining rect', function () {
    $window = new FakeWindow('main');
    $tabs = tabsUnderTest($window);

    // Fake hug is 80x30; pad 8, gap 4; centred in the 32 strip → y 1.
    expect($tabs->part('tab.a')->frame())->toBe(['x' => 8, 'y' => 1, 'width' => 80, 'height' => 30])
        ->and($tabs->part('tab.b')->frame())->toBe(['x' => 92, 'y' => 1, 'width' => 80, 'height' => 30])
        ->and($tabs->part('panel.a')->frame())->toBe(['x' => 0, 'y' => 32, 'width' => 300, 'height' => 168])
        ->and($tabs->part('panel.b')->frame())->toBe(['x' => 0, 'y' => 32, 'width' => 300, 'height' => 168]);
});

it('a user press on another tab hides the previous panel, shows the new one, and fires the hook', function () {
    $window = new FakeWindow('main');
    $tabs = tabsUnderTest($window);
    $picked = [];
    $tabs->onSelect(function (string $key) use (&$picked) { $picked[] = $key; });

    /** @var FakeToggleButton $beta */
    $beta = $window->view('pages.tab.b');
    $beta->press(true);

    expect($tabs->selectedKey())->toBe('b')
        ->and($picked)->toBe(['b'])
        ->and($window->view('pages.panel.a')->isVisible())->toBeFalse()
        ->and($window->view('pages.panel.b')->isVisible())->toBeTrue()
        ->and($window->view('pages.tab.a')->isPressed())->toBeFalse();
});

it('programmatic select stays silent', function () {
    $window = new FakeWindow('main');
    $tabs = tabsUnderTest($window);
    $picked = [];
    $tabs->onSelect(function (string $key) use (&$picked) { $picked[] = $key; });

    $tabs->select('b');

    expect($picked)->toBe([])
        ->and($tabs->selectedKey())->toBe('b')
        ->and($window->view('pages.panel.a')->isVisible())->toBeFalse()
        ->and($window->view('pages.panel.b')->isVisible())->toBeTrue();
});

it('unpressing the selected tab snaps it back — selection is sticky', function () {
    $window = new FakeWindow('main');
    $tabs = tabsUnderTest($window);

    /** @var FakeToggleButton $alpha */
    $alpha = $window->view('pages.tab.a');
    $alpha->press(false);

    expect($alpha->isPressed())->toBeTrue()
        ->and($tabs->selectedKey())->toBe('a');
});

it('the first tab selects itself silently', function () {
    $window = new FakeWindow('main');
    $tabs = new Tabs($window, 'pages', 0, 0, 300, 200);
    $picked = [];
    $tabs->onSelect(function (string $key) use (&$picked) { $picked[] = $key; });

    $tabs->addTab('a', 'Alpha');

    expect($tabs->selectedKey())->toBe('a')
        ->and($picked)->toBe([]);
});

it('place to a taller frame grows the panels', function () {
    $window = new FakeWindow('main');
    $tabs = tabsUnderTest($window);

    $tabs->place(0, 0, 300, 400);

    expect($tabs->part('panel.a')->frame())->toBe(['x' => 0, 'y' => 32, 'width' => 300, 'height' => 368])
        ->and($tabs->part('panel.b')->frame()['height'])->toBe(368);
});

it('refuses a duplicate tab key and selecting a ghost', function () {
    $window = new FakeWindow('main');
    $tabs = tabsUnderTest($window);

    expect(fn () => $tabs->addTab('a', 'Again'))
        ->toThrow(WindowableException::class, "already has a tab 'a'")
        ->and(fn () => $tabs->select('ghost'))
        ->toThrow(WindowableException::class, "has no tab 'ghost'");
});

it('removal frees the root, tab, and panel names', function () {
    $window = new FakeWindow('main');
    $tabs = tabsUnderTest($window);

    $tabs->remove();

    expect($window->view('pages'))->toBeNull()
        ->and($window->view('pages.tab.a'))->toBeNull()
        ->and($window->view('pages.tab.b'))->toBeNull()
        ->and($window->view('pages.panel.a'))->toBeNull()
        ->and($window->view('pages.panel.b'))->toBeNull();
});
