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
use Surface\Contracts\Windows\Mail\Staged\StagedWheeled;
use Surface\Contracts\Windows\Mail\View\ScrollPhase;
use Surface\Contracts\Windows\Mail\WindowEventOccurred;
use Surface\Contracts\Windows\WindowCapability;
use Venusian\Surface\Tests\Fixtures\FakeSession;

/*
 * The staged window's input mail: the canvas mail's shape, keyed by window only.
 */

beforeEach(fn () => FakeStagedWindow::$capabilities = WindowCapability::cases());
afterEach(fn () => FakeStagedWindow::$capabilities = []);

dataset('staged mail', function () {
    $mods = new Modifiers(shift: true);

    return [
        'pressed' => [new StagedPressed('game', 3.5, 4.0, MouseButton::LEFT, 2, $mods), 'staged.pressed.game'],
        'released' => [new StagedReleased('game', 3.5, 4.0, MouseButton::LEFT, $mods), 'staged.released.game'],
        'moved' => [new StagedMoved('game', 1.0, 2.0, $mods), 'staged.moved.game'],
        'dragged' => [new StagedDragged('game', 1.0, 2.0, MouseButton::RIGHT, $mods), 'staged.dragged.game'],
        'wheeled' => [new StagedWheeled('game', 0.0, -3.0, true, $mods), 'staged.wheeled.game'],
        'entered' => [new StagedEntered('game'), 'staged.entered.game'],
        'left' => [new StagedLeft('game'), 'staged.left.game'],
        'key pressed' => [new StagedKeyPressed('game', Key::A, 'A', $mods, false), 'staged.key-pressed.game'],
        'key released' => [new StagedKeyReleased('game', Key::A, $mods), 'staged.key-released.game'],
        'text committed' => [new StagedTextCommitted('game', 'é'), 'staged.text-committed.game'],
        'focus gained' => [new StagedFocusGained('game'), 'staged.focus-gained.game'],
        'focus lost' => [new StagedFocusLost('game'), 'staged.focus-lost.game'],
    ];
});

it('names staged mail by verb and window', function (WindowEventOccurred $mail, string $name) {
    expect($mail->name())->toBe($name)
        ->and($mail->window())->toBe('game')
        ->and((new ReflectionClass($mail))->isReadOnly())->toBeTrue();
})->with('staged mail');

it('mirrors the canvas fields', function () {
    $pressed = new StagedPressed('game', 3.5, 4.0, MouseButton::LEFT, 2, new Modifiers());
    $wheeled = new StagedWheeled('game', 0.0, -3.0, true, new Modifiers());

    expect([$pressed->x, $pressed->y, $pressed->button, $pressed->clicks])->toBe([3.5, 4.0, MouseButton::LEFT, 2])
        ->and($wheeled->phase)->toBe(ScrollPhase::NONE)
        ->and(fn () => $pressed->x = 9.0)->toThrow(Error::class);
});

it('watches input only when asked', function () {
    $window = (new FakeStager(new FakeSession()))->openStaged('game', 320, 240, []);

    expect($window->watchesInput())->toBeFalse()
        ->and($window->watchInput())->toBe($window)
        ->and($window->watchesInput())->toBeTrue()
        ->and($window->watchInput(false)->watchesInput())->toBeFalse();
});
