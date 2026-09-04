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

it('every click rides the dock as ButtonClicked mail named <window>.<name>.clicked', function () {
    $dock = bareDock();
    $window = new FakeWindow('main');
    $window->setPool($dock);
    $button = $window->button('go', 'Go', 0, 0, 80, 30);

    $button->click();

    $event = $dock->drain()->first(
        fn (object $mail) => $mail instanceof \Surface\Contracts\NativeWindows\Events\View\ButtonClicked,
    );
    expect($event)->not->toBeNull()
        ->and($event->name)->toBe('main.go.clicked');
});

it('starts enabled, and setEnabled writes through only on change', function () {
    $window = new FakeWindow('main');
    /** @var \Venusian\Surface\Tests\Support\Fakes\FakeButton $button */
    $button = $window->button('go', 'Go', 0, 0, 80, 30);

    expect($button->isEnabled())->toBeTrue()
        ->and($button->applied_enabled)->toBe([]);

    $button->disable();
    $button->disable();
    $button->enable();

    expect($button->isEnabled())->toBeTrue()
        ->and($button->applied_enabled)->toBe([false, true]);
});

it('clicking with no pool and no hook is still safe', function () {
    $window = new FakeWindow('main');
    $window->button('go', 'Go', 0, 0, 80, 30)->click();

    expect(true)->toBeTrue();
});
