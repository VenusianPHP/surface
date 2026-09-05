<?php

use Surface\NativeWindows\Components\MessageSeverity;
use Surface\NativeWindows\Components\Toast;
use Venusian\Surface\Tests\Support\Fakes\FakeGroup;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('push stacks messages from y=0 with a gap of 8 and returns the part key', function () {
    $window = new FakeWindow('main');
    $toast = new Toast($window, 'toasts', 0, 0, 300, 200);

    $first = $toast->push('Saved.');
    $second = $toast->push('Also saved.', MessageSeverity::SUCCESS);

    expect($first)->toBe('msg.1')
        ->and($second)->toBe('msg.2')
        ->and($window->view('toasts.msg.1'))->toBeInstanceOf(FakeGroup::class)
        ->and($toast->part('msg.1')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 300, 'height' => 40])
        ->and($toast->part('msg.2')->frame())->toBe(['x' => 0, 'y' => 48, 'width' => 300, 'height' => 40]);
});

it('closing the first toast drops its names and slides the rest up', function () {
    $window = new FakeWindow('main');
    $toast = new Toast($window, 'toasts', 0, 0, 300, 200);
    $toast->push('One.');
    $toast->push('Two.');

    $window->view('toasts.msg.1.close')->click();

    expect($window->view('toasts.msg.1'))->toBeNull()
        ->and($window->view('toasts.msg.1.text'))->toBeNull()
        ->and($window->view('toasts.msg.1.close'))->toBeNull()
        ->and($toast->part('msg.1'))->toBeNull()
        ->and($toast->part('msg.2')->frame()['y'])->toBe(0)
        ->and($window->view('toasts.msg.2'))->not->toBeNull();
});

it('place stretches remaining toasts to the new inner width', function () {
    $window = new FakeWindow('main');
    $toast = new Toast($window, 'toasts', 0, 0, 300, 200);
    $toast->push('Saved.');

    $toast->place(0, 0, 400, 200);

    expect($toast->part('msg.1')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 400, 'height' => 40]);
});

it('removal frees the host and every nested message name', function () {
    $window = new FakeWindow('main');
    $toast = new Toast($window, 'toasts', 0, 0, 300, 200);
    $toast->push('Saved.');
    $toast->push('Again.');

    $toast->remove();

    expect($window->view('toasts'))->toBeNull()
        ->and($window->view('toasts.msg.1'))->toBeNull()
        ->and($window->view('toasts.msg.1.text'))->toBeNull()
        ->and($window->view('toasts.msg.2'))->toBeNull();
});
