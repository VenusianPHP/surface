<?php

declare(strict_types=1);

use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\Modifiers;
use Surface\Contracts\HumanInput\MouseButton;
use Surface\Contracts\Windows\Mail\Staged\StagedDragged;
use Surface\Contracts\Windows\Mail\Staged\StagedEntered;
use Surface\Contracts\Windows\Mail\Staged\StagedFocusGained;
use Surface\Contracts\Windows\Mail\Staged\StagedFocusLost;
use Surface\Contracts\Windows\Mail\Staged\StagedKeyPressed;
use Surface\Contracts\Windows\Mail\Staged\StagedKeyReleased;
use Surface\Contracts\Windows\Mail\Staged\StagedLeft;
use Surface\Contracts\Windows\Mail\Staged\StagedMoved;
use Surface\Contracts\Windows\Mail\Staged\StagedPressed;
use Surface\Contracts\Windows\Mail\Staged\StagedReleased;
use Surface\Contracts\Windows\Mail\Staged\StagedTextCommitted;
use Surface\Contracts\Windows\Mail\Staged\StagedEditCommanded;
use Surface\Contracts\Windows\Input\EditCommand;
use Surface\Contracts\Windows\Mail\Staged\StagedWheeled;
use Surface\Contracts\Windows\Mail\View\ScrollPhase;
use Surface\Contracts\Windows\Mail\WindowFocused;
use Surface\Contracts\Windows\WindowCapability;
use Venusian\Surface\Tests\Fixtures\FakeSession;

/*
 * What a stager's native input callbacks post: the staged input mail, only while
 * watchInput() is on, in the order the events came.
 */

beforeEach(fn () => FakeStagedWindow::$capabilities = WindowCapability::cases());
afterEach(fn () => FakeStagedWindow::$capabilities = []);

function watched(bool $watching = true): FakeStagedWindow
{
    $window = (new FakeStager(new FakeSession()))->openStaged('game', 320, 240, []);

    return $watching ? $window->watchInput() : $window;
}

it('posts each input callback as its mail, in order', function () {
    $window = watched();
    $mods = new Modifiers(shift: true);

    $window->nativeInput('pointerEntered');
    $window->nativeInput('pressed', 3.5, 4.0, MouseButton::LEFT, 2, $mods);
    $window->nativeInput('dragged', 5.0, 6.0, MouseButton::LEFT, $mods);
    $window->nativeInput('released', 5.0, 6.0, MouseButton::LEFT, $mods);
    $window->nativeInput('wheeled', 0.0, -3.0, true, $mods, ScrollPhase::NONE);
    $window->nativeInput('keyPressed', Key::A, 'A', $mods, false);
    $window->nativeInput('textCommitted', 'é');
    $window->nativeInput('editCommanded', EditCommand::Paste);
    $window->nativeInput('keyReleased', Key::A, $mods);
    $window->nativeInput('pointerLeft');

    expect($window->session()->outbox())->toEqual([
        new StagedEntered('game'),
        new StagedPressed('game', 3.5, 4.0, MouseButton::LEFT, 2, $mods),
        new StagedDragged('game', 5.0, 6.0, MouseButton::LEFT, $mods),
        new StagedReleased('game', 5.0, 6.0, MouseButton::LEFT, $mods),
        new StagedWheeled('game', 0.0, -3.0, true, $mods),
        new StagedKeyPressed('game', Key::A, 'A', $mods, false),
        new StagedTextCommitted('game', 'é'),
        new StagedEditCommanded('game', EditCommand::Paste),
        new StagedKeyReleased('game', Key::A, $mods),
        new StagedLeft('game'),
    ]);
});

it('posts a focus change as its staged mail after the window mail', function () {
    $window = watched();

    $window->focus();
    $window->blur();

    expect($window->session()->outbox())->toEqual([
        new WindowFocused('game'), new StagedFocusGained('game'),
        new \Surface\Contracts\Windows\Mail\WindowFocusLost('game'), new StagedFocusLost('game'),
    ]);
});

it('holds a move as latest mail and sends it before the next input mail', function () {
    $window = watched();
    $mods = new Modifiers();

    $window->nativeInput('pointerMoved', 1.0, 1.0, $mods);
    $window->nativeInput('pointerMoved', 2.0, 2.0, $mods);

    expect($window->session()->outbox())->toBe([]);

    $window->nativeInput('pressed', 2.0, 2.0, MouseButton::LEFT, 1, $mods);

    expect($window->session()->outbox())->toEqual([
        new StagedMoved('game', 2.0, 2.0, $mods),
        new StagedPressed('game', 2.0, 2.0, MouseButton::LEFT, 1, $mods),
    ]);
});

it('posts no input mail while it is not watching', function () {
    $window = watched(false);
    $mods = new Modifiers();

    $window->nativeInput('pointerEntered');
    $window->nativeInput('pressed', 1.0, 1.0, MouseButton::LEFT, 1, $mods);
    $window->nativeInput('pointerMoved', 1.0, 1.0, $mods);
    $window->nativeInput('keyPressed', Key::A, 'A', $mods, false);
    $window->focus();
    $window->session()->flushLatest();

    expect($window->session()->outbox())->toEqual([new WindowFocused('game')]);
});

it('posts no input mail once closed', function () {
    $window = watched();
    $window->close();
    $before = count($window->session()->outbox());

    $window->nativeInput('pressed', 1.0, 1.0, MouseButton::LEFT, 1, new Modifiers());
    $window->nativeInput('textCommitted', 'x');
    $window->nativeInput('editCommanded', EditCommand::Paste);
    $window->nativeInput('pointerLeft');
    $window->session()->flushLatest();

    expect(count($window->session()->outbox()))->toBe($before);
});
