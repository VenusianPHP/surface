<?php

use Surface\Contracts\NativeWindows\Views\ContainerHandle;
use Surface\Contracts\NativeWindows\Views\ViewException;
use Surface\NativeWindows\Enums\Orientation;
use Surface\NativeWindows\Enums\ViewType;
use Surface\NativeWindows\Views\Color;
use Surface\NativeWindows\Views\Frame;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeBox;
use Venusian\Surface\Tests\Views\Fakes\FakeWindow;

dataset('contents', [
    'text' => [fn (ContainerHandle $b) => $b->text('c', 'Hi', 1, 2, 30, 40), ViewType::TEXT, ['createText', 'attach', 'setFrame']],
    'image' => [fn (ContainerHandle $b) => $b->image('c', __FILE__, 1, 2, 30, 40), ViewType::IMAGE, ['createImage', 'attach', 'setFrame', 'setFit']],
    'scroll' => [fn (ContainerHandle $b) => $b->scroll('c', 80, 200, 1, 2, 30, 40), ViewType::SCROLL, ['createScroll', 'attach', 'setFrame']],
    'split' => [fn (ContainerHandle $b) => $b->split('c', Orientation::HORIZONTAL, 1, 2, 30, 40, 12), ViewType::SPLIT, ['createSplit', 'attach', 'setFrame', 'setDivider']],
    'tabs' => [fn (ContainerHandle $b) => $b->tabs('c', 1, 2, 30, 40), ViewType::TABS, ['createTabs', 'attach', 'setFrame']],
    'popover' => [fn (ContainerHandle $b) => $b->popover('c', 30, 40), ViewType::POPOVER, ['createPopover']],
]);

it('conjures every wave-3 type into the tree', function (Closure $conjure, ViewType $type, array $ops) {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $log->clear();

    $view = $conjure($root);

    expect($view->type())->toBe($type)
        ->and($view->path())->toBe('c')
        ->and($root->find('c'))->toBe($view)
        ->and($log->ops())->toBe($ops);
})->with('contents');

it('refuses a second wave-3 view under the same nickname', function (Closure $conjure) {
    $root = new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 640, 480));
    $conjure($root);

    $conjure($root);
})->with('contents')->throws(ViewException::class, "'c'");

it('removes every wave-3 view terminally and leaves a dead handle', function (Closure $conjure) {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $view = $conjure($root);
    $log->clear();

    $view->remove();

    expect($log->of('detach')[0]['pointer'])->toBe($view->pointer())
        ->and($root->has('c'))->toBeFalse()
        ->and($root->find('c'))->toBeNull()
        ->and($view->isAlive())->toBeFalse()
        ->and(fn () => $view->bgColor(Color::hex('#000')))->toThrow(ViewException::class, "'c'");
})->with('contents');

it('kills every wave-3 view with the window', function (Closure $conjure) {
    $window = new FakeWindow(new CallLog);
    $view = $conjure($window->root());

    $window->close();

    expect($view->isAlive())->toBeFalse()
        ->and(fn () => $view->bgColor(Color::hex('#000')))->toThrow(ViewException::class, "'c'");
})->with('contents');
