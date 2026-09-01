<?php

use Surface\Contracts\NativeWindows\WindowableException;
use Venusian\Surface\Tests\Support\Fakes\FakeButton;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('conjures a button, registers it by name and places it at once', function () {
    $window = new FakeWindow('main');

    $button = $window->button('go', 'Go', 10, 20, 80, 30);

    expect($button)->toBeInstanceOf(FakeButton::class)
        ->and($button->label())->toBe('Go')
        ->and($window->view('go'))->toBe($button)
        ->and($button->applied_frames)->toBe([[10, 20, 80, 30]]);
});

it('refuses a name already taken by any view kind', function () {
    $window = new FakeWindow('main');
    $window->label('go', 'text', 0, 0, 1, 1);

    expect(fn () => $window->button('go', 'Go', 0, 0, 1, 1))
        ->toThrow(WindowableException::class, "View 'go' already exists");
});

it('invokes the click hook, once per click', function () {
    $window = new FakeWindow('main');
    $clicks = 0;
    $button = $window->button('go', 'Go', 0, 0, 80, 30)
        ->onClick(function () use (&$clicks) { $clicks++; });

    $button->click();
    $button->click();

    expect($clicks)->toBe(2);
});

it('survives a click with no hook attached', function () {
    $window = new FakeWindow('main');
    $button = $window->button('go', 'Go', 0, 0, 80, 30);

    $button->click();

    expect(true)->toBeTrue();
});

it('replaces the hook rather than stacking', function () {
    $window = new FakeWindow('main');
    $log = [];
    $button = $window->button('go', 'Go', 0, 0, 80, 30);
    $button->onClick(function () use (&$log) { $log[] = 'first'; });
    $button->onClick(function () use (&$log) { $log[] = 'second'; });

    $button->click();

    expect($log)->toBe(['second']);
});

it('writes the label through to the engine and remembers it', function () {
    $window = new FakeWindow('main');
    $button = $window->button('go', 'Go', 0, 0, 80, 30);

    $button->setLabel('Clicked: 3');

    expect($button->label())->toBe('Clicked: 3')
        ->and($button->applied_labels)->toBe(['Clicked: 3']);
});

it('inherits the placement rules: hug then center re-resolve on relayout', function () {
    $window = new FakeWindow('main');
    $window->content_size = [400, 600];
    $button = $window->button('go', 'Go', 0, 0, 1, 1)->hug()->center();

    $window->content_size = [800, 300];
    $window->relayout();

    expect($button->frame())->toBe(['x' => 360, 'y' => 135, 'width' => 80, 'height' => 30]);
});

it('removal is terminal and frees the name', function () {
    $window = new FakeWindow('main');
    $button = $window->button('go', 'Go', 0, 0, 1, 1);

    $button->remove();

    expect($button->destroyed)->toBeTrue()
        ->and($window->view('go'))->toBeNull();
});

it('every click rides the queue as view.clicked.<window>.<name>', function () {
    $queue = new \Voyager\IOPools\EventQueue();
    $window = new FakeWindow('main');
    $window->setEventSink($queue);
    $button = $window->button('go', 'Go', 0, 0, 80, 30);

    $button->click();

    $event = $queue->drain()->get('view.clicked.main.go');
    expect($event->type)->toBe(\Surface\Contracts\NativeWindows\Events\SurfaceEventType::VIEW_CLICKED)
        ->and($event->window)->toBe('main');
});

it('clicking with no sink and no hook is still safe', function () {
    $window = new FakeWindow('main');
    $window->button('go', 'Go', 0, 0, 80, 30)->click();

    expect(true)->toBeTrue();
});
