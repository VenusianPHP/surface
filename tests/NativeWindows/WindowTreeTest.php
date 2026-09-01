<?php

use Surface\Contracts\NativeWindows\Views\ViewException;
use Surface\NativeWindows\Views\Frame;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeApplication;
use Venusian\Surface\Tests\Views\Fakes\FakeWindow;

it('hands over one lazily built root wrapping the content pointer', function () {
    $window = new FakeWindow(new CallLog, 640, 480);

    $root = $window->root();

    expect($window->root())->toBe($root)
        ->and($root->pointer())->toBe(2)
        ->and($root->isRoot())->toBeTrue()
        ->and($root->frame())->toEqual(new Frame(0, 0, 640, 480));
});

it('answers dotted paths from the root', function () {
    $window = new FakeWindow(new CallLog);
    $title = $window->root()->box('side', 0, 0, 200, 480)->label('title', 'Hi', 0, 0, 10, 10);

    expect($window->getFromTree('side.title'))->toBe($title)
        ->and($window->getFromTree('side'))->toBe($title->parent())
        ->and($window->getFromTree(''))->toBe($window->root())
        ->and($window->getFromTree('nope'))->toBeNull();
});

it('removes by path through the same terminal removal', function () {
    $log = new CallLog;
    $window = new FakeWindow($log);
    $title = $window->root()->box('side', 0, 0, 200, 480)->label('title', 'Hi', 0, 0, 10, 10);
    $log->clear();

    $window->removeFromTree('side.title');

    expect($log->ops())->toBe(['detach'])
        ->and($title->isAlive())->toBeFalse()
        ->and($window->getFromTree('side.title'))->toBeNull();
});

it('throws when removing a path that is not there', function () {
    (new FakeWindow(new CallLog))->removeFromTree('side.ghost');
})->throws(ViewException::class, 'side.ghost');

it('refuses to remove the root by path', function () {
    (new FakeWindow(new CallLog))->removeFromTree('');
})->throws(ViewException::class, 'Root');

it('keeps the root frame in step with the native content size', function () {
    $log = new CallLog;
    $window = new FakeWindow($log, 640, 480);
    $root = $window->root();
    $window->native_width = 800;
    $window->native_height = 600;

    expect($window->pollResize())->toBeTrue()
        ->and($root->frame())->toEqual(new Frame(0, 0, 800, 600))
        ->and($window->pollResize())->toBeFalse()
        ->and($log->entries)->toBe([]);
});

it('builds the root at the current size when resized before first use', function () {
    $window = new FakeWindow(new CallLog, 640, 480);
    $window->native_width = 1024;
    $window->native_height = 768;
    $window->pollResize();

    expect($window->root()->frame())->toEqual(new Frame(0, 0, 1024, 768));
});

it('lets the application reach any window tree by name', function () {
    $app = new FakeApplication(new CallLog);
    $window = null;
    $app->createWindow('main', 640, 480, $window);
    $title = $window->root()->label('title', 'Hi', 0, 0, 10, 10);

    expect($app->getWindow('main'))->toBe($window)
        ->and($app->getWindow('main')->getFromTree('title'))->toBe($title)
        ->and($app->getWindow('ghost'))->toBeNull();
});
