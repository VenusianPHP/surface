<?php

use Surface\Contracts\NativeWindows\WindowableException;
use Venusian\Surface\Tests\Support\Fakes\FakeButton;
use Venusian\Surface\Tests\Support\Fakes\FakeGroup;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('conjures a group, registers it by name and places it at once', function () {
    $window = new FakeWindow('main');

    $group = $window->group('card', 10, 20, 200, 100);

    expect($group)->toBeInstanceOf(FakeGroup::class)
        ->and($window->view('card'))->toBe($group)
        ->and($group->applied_frames)->toBe([[10, 20, 200, 100]])
        ->and($group->innerSize())->toBe([200, 100]);
});

it('children conjured into a group register window-globally and lay out group-relative', function () {
    $window = new FakeWindow('main');
    $group = $window->group('card', 50, 50, 200, 100);

    $button = $group->button('go', 'Go', 10, 10, 80, 30);

    expect($button)->toBeInstanceOf(FakeButton::class)
        ->and($window->view('go'))->toBe($button)
        ->and($button->hostedBy())->toBe($group)
        ->and($group->children())->toBe([$button])
        // The frame is group-relative — the engine parented the native
        // under the group, so 10,10 means 10,10 inside the card.
        ->and($button->applied_frames)->toBe([[10, 10, 80, 30]]);
});

it('a child name collides with any window view, even across groups', function () {
    $window = new FakeWindow('main');
    $window->label('go', 'text', 0, 0, 1, 1);
    $group = $window->group('card', 0, 0, 200, 100);

    expect(fn () => $group->button('go', 'Go', 0, 0, 1, 1))
        ->toThrow(WindowableException::class, "View 'go' already exists");
});

it('a centered child resolves against the group inner size, not the window', function () {
    $window = new FakeWindow('main');
    $window->content_size = [800, 600];
    $group = $window->group('card', 50, 50, 200, 100);

    $button = $group->button('go', 'Go', 0, 0, 1, 1)->hug()->center();

    // Natural 80x30 centered in 200x100 → (60, 35). Against the window it
    // would have been (360, 285).
    expect($button->frame())->toBe(['x' => 60, 'y' => 35, 'width' => 80, 'height' => 30]);
});

it('relayout cascades from the group into its children', function () {
    $window = new FakeWindow('main');
    $group = $window->group('card', 0, 0, 200, 100);
    $button = $group->button('go', 'Go', 0, 0, 1, 1)->hug()->center();

    $group->place(0, 0, 400, 100);

    // The group grew; the centered child re-resolved against the new space.
    expect($button->frame()['x'])->toBe(160);
});

it('nested groups resolve against their immediate host', function () {
    $window = new FakeWindow('main');
    $outer = $window->group('outer', 0, 0, 400, 400);
    $inner = $outer->group('inner', 0, 0, 100, 50);
    $button = $inner->button('go', 'Go', 0, 0, 1, 1)->hug()->center();

    expect($inner->hostedBy())->toBe($outer)
        ->and($button->frame())->toBe(['x' => 10, 'y' => 10, 'width' => 80, 'height' => 30]);
});

it('removal is terminal for the subtree and frees every name', function () {
    $window = new FakeWindow('main');
    $group = $window->group('card', 0, 0, 200, 100);
    $button = $group->button('go', 'Go', 0, 0, 80, 30);
    $inner = $group->group('inner', 0, 0, 50, 50);
    $label = $inner->label('note', 'hi', 0, 0, 40, 20);

    $group->remove();

    expect($group->destroyed)->toBeTrue()
        ->and($button->destroyed)->toBeTrue()
        ->and($window->view('card'))->toBeNull()
        ->and($window->view('go'))->toBeNull()
        ->and($window->view('inner'))->toBeNull()
        ->and($window->view('note'))->toBeNull();

    // The names are free again.
    $window->button('go', 'Again', 0, 0, 1, 1);
    expect($window->view('go'))->not->toBeNull();
});

it('a top-level view still resolves against the window content', function () {
    $window = new FakeWindow('main');
    $window->content_size = [400, 600];

    $button = $window->button('go', 'Go', 0, 0, 1, 1)->hug()->center();

    expect($button->hostedBy())->toBeNull()
        ->and($button->frame())->toBe(['x' => 160, 'y' => 285, 'width' => 80, 'height' => 30]);
});
