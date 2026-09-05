<?php

use Surface\Contracts\NativeWindows\WindowableException;
use Surface\NativeWindows\Components\Breadcrumb;
use Venusian\Surface\Tests\Support\Fakes\FakeButton;
use Venusian\Surface\Tests\Support\Fakes\FakeLabel;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

function breadcrumbUnderTest(FakeWindow $window): Breadcrumb
{
    $trail = new Breadcrumb($window, 'path', 0, 0, 500, 44);
    $trail->addItem('home', 'Home');
    $trail->addItem('docs', 'Docs');
    $trail->addItem('api', 'API');

    return $trail;
}

it('flows items left to right with separators, last item disabled', function () {
    $window = new FakeWindow('main');
    $trail = breadcrumbUnderTest($window);

    expect($window->view('path.item.home'))->toBeInstanceOf(FakeButton::class)
        ->and($window->view('path.sep.1'))->toBeInstanceOf(FakeLabel::class)
        ->and($window->view('path.sep.1')->text())->toBe('›')
        ->and($window->view('path.sep.2'))->not->toBeNull()
        ->and($window->view('path.sep.3'))->toBeNull()
        ->and($trail->part('item.home')->isEnabled())->toBeTrue()
        ->and($trail->part('item.docs')->isEnabled())->toBeTrue()
        ->and($trail->part('item.api')->isEnabled())->toBeFalse()
        // Fake hug is 80x30; pad 8, gap 8; seps 12x18; centred on 44.
        ->and($trail->part('item.home')->frame())->toBe(['x' => 8, 'y' => 7, 'width' => 80, 'height' => 30])
        ->and($trail->part('sep.1')->frame())->toBe(['x' => 96, 'y' => 13, 'width' => 12, 'height' => 18])
        ->and($trail->part('item.docs')->frame())->toBe(['x' => 116, 'y' => 7, 'width' => 80, 'height' => 30])
        ->and($trail->part('sep.2')->frame())->toBe(['x' => 204, 'y' => 13, 'width' => 12, 'height' => 18])
        ->and($trail->part('item.api')->frame())->toBe(['x' => 224, 'y' => 7, 'width' => 80, 'height' => 30]);
});

it('clicking an enabled item fires onSelect; clicking the last item does not', function () {
    $window = new FakeWindow('main');
    $trail = breadcrumbUnderTest($window);
    $picked = [];
    $trail->onSelect(function (string $key) use (&$picked) { $picked[] = $key; });

    $trail->part('item.home')->click();
    $trail->part('item.api')->click();

    expect($picked)->toBe(['home']);
});

it('appending a new item enables the previous last so its click now fires', function () {
    $window = new FakeWindow('main');
    $trail = new Breadcrumb($window, 'path', 0, 0, 500, 44);
    $trail->addItem('home', 'Home');
    $picked = [];
    $trail->onSelect(function (string $key) use (&$picked) { $picked[] = $key; });

    $trail->part('item.home')->click();
    expect($picked)->toBe([]);

    $trail->addItem('docs', 'Docs');
    $trail->part('item.home')->click();

    expect($trail->part('item.home')->isEnabled())->toBeTrue()
        ->and($trail->part('item.docs')->isEnabled())->toBeFalse()
        ->and($picked)->toBe(['home']);
});

it('refuses a duplicate item key', function () {
    $window = new FakeWindow('main');
    $trail = breadcrumbUnderTest($window);

    expect(fn () => $trail->addItem('home', 'Again'))
        ->toThrow(WindowableException::class, "already has an item 'home'");
});

it('place re-flows the trail in the new frame', function () {
    $window = new FakeWindow('main');
    $trail = breadcrumbUnderTest($window);

    $trail->place(0, 0, 600, 60);

    expect($trail->part('item.home')->frame())->toBe(['x' => 8, 'y' => 15, 'width' => 80, 'height' => 30])
        ->and($trail->part('sep.1')->frame())->toBe(['x' => 96, 'y' => 21, 'width' => 12, 'height' => 18]);
});

it('removal frees the root, items and separators', function () {
    $window = new FakeWindow('main');
    $trail = breadcrumbUnderTest($window);

    $trail->remove();

    expect($window->view('path'))->toBeNull()
        ->and($window->view('path.item.home'))->toBeNull()
        ->and($window->view('path.sep.1'))->toBeNull()
        ->and($window->view('path.item.api'))->toBeNull();
});
