<?php

use Surface\Contracts\NativeWindows\Views\ViewException;
use Surface\NativeWindows\Views\Frame;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeWindow;

function openWindow(CallLog $log): FakeWindow
{
    $window = new FakeWindow($log, 640, 480);
    $side = $window->root()->box('side', 0, 0, 200, 480);
    $side->label('title', 'Hi', 0, 0, 100, 20);
    $window->root()->button('go', 'Go', 0, 0, 80, 30);
    $log->clear();

    return $window;
}

it('kills every handle before destroying the native window', function () {
    $log = new CallLog;
    $window = openWindow($log);
    $root = $window->root();
    $side = $root->child('side');
    $title = $root->find('side.title');
    $go = $root->child('go');

    $window->close();

    expect($log->ops())->toBe(['closeWindow'])
        ->and($root->isAlive())->toBeFalse()
        ->and($side->isAlive())->toBeFalse()
        ->and($title->isAlive())->toBeFalse()
        ->and($go->isAlive())->toBeFalse()
        ->and($window->root())->toBe($root)
        ->and($window->getFromTree('side.title'))->toBeNull()
        ->and($title->frame())->toEqual(new Frame(0, 0, 100, 20));
});

it('is idempotent', function () {
    $log = new CallLog;
    $window = openWindow($log);

    $window->close();
    $window->close();

    expect($log->ops())->toBe(['closeWindow']);
});

it('refuses to conjure after close', function () {
    $window = openWindow(new CallLog);
    $window->close();

    $window->root()->label('late', 'x', 0, 0, 1, 1);
})->throws(ViewException::class, 'root');

it('refuses native calls on handles that outlived the window', function () {
    $window = openWindow(new CallLog);
    $go = $window->root()->child('go');
    $window->close();

    $go->title('x');
})->throws(ViewException::class, 'go');

it('treats the OS closing the window the same way, without a second native close', function () {
    $log = new CallLog;
    $window = openWindow($log);
    $go = $window->root()->child('go');

    $window->simulateOsClose();

    expect($go->isAlive())->toBeFalse()
        ->and($log->ops())->toBe([]);

    $window->close();

    expect($log->ops())->toBe([]);
});

it('hands over a dead root when the tree was never built before close', function () {
    $log = new CallLog;
    $window = new FakeWindow($log, 640, 480);

    $window->close();
    $root = $window->root();

    expect($root->isAlive())->toBeFalse()
        ->and(fn () => $root->label('late', 'x', 0, 0, 1, 1))->toThrow(ViewException::class);
});
