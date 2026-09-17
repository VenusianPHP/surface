<?php

use Surface\Contracts\HumanInput\GamepadAxis;
use Surface\Contracts\HumanInput\GamepadButton;
use Surface\Contracts\HumanInput\HumanInputException;
use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\Modifiers;
use Surface\Contracts\HumanInput\MouseButton;
use Surface\HumanInput\Devices\DigitalButton;
use Surface\HumanInput\Devices\GameController;
use Surface\HumanInput\Devices\GamePad;
use Surface\HumanInput\Devices\Keyboard;
use Surface\HumanInput\Devices\Mouse;

it('holds an edge for the poll that saw it', function () {
    $b = new DigitalButton('a');

    $b->settle(); $b->update(true, 1_000_000);
    expect([$b->isDown(), $b->isPressed(), $b->wasReleased()])->toBe([true, true, false]);

    $b->settle(); $b->update(true, 2_000_000);
    expect([$b->isDown(), $b->isPressed()])->toBe([true, false]);

    $b->settle(); $b->update(false, 3_000_000);
    expect([$b->isDown(), $b->wasReleased()])->toBe([false, true]);

    $b->settle();
    expect($b->wasReleased())->toBeFalse();
});

it('keeps both edges of a tap that starts and ends inside one poll', function () {
    $b = new DigitalButton('a');

    $b->settle(); $b->update(true, 1); $b->update(false, 2);

    expect([$b->isDown(), $b->isPressed(), $b->wasReleased()])->toBe([false, true, true]);
});

it('measures a hold from the press', function () {
    $b = new DigitalButton('a');
    $b->update(true, hrtime(true) - 30_000_000);

    expect($b->heldMs())->toBeGreaterThanOrEqual(30)
        ->and($b->isHolding(20))->toBeTrue()
        ->and($b->isHolding(60_000))->toBeFalse();

    $b->update(false);
    expect($b->heldMs())->toBe(0);
});

it('tracks keys, text and modifiers, and forgets text on settle', function () {
    $kb = new Keyboard();
    $kb->settle();
    $kb->update(Key::W, true)->update(Key::LEFT_SHIFT, true)->appendText('W')->setModifiers(new Modifiers(shift: true));

    expect($kb->isPressed(Key::W))->toBeTrue()
        ->and($kb->downKeys())->toBe([Key::W, Key::LEFT_SHIFT])
        ->and($kb->pressedKeys())->toBe([Key::W, Key::LEFT_SHIFT])
        ->and($kb->text())->toBe('W')
        ->and($kb->modifiers()->shift)->toBeTrue()
        ->and($kb->isDown(Key::A))->toBeFalse();

    $kb->settle();
    $kb->update(Key::W, false);

    expect($kb->text())->toBe('')
        ->and($kb->releasedKeys())->toBe([Key::W])
        ->and($kb->downKeys())->toBe([Key::LEFT_SHIFT]);
});

it('releases everything on reset', function () {
    $kb = (new Keyboard())->update(Key::A, true);
    $kb->reset();

    expect($kb->downKeys())->toBe([]);
});

it('tracks the pointer, accumulating motion and wheel between settles', function () {
    $m = new Mouse();
    $m->settle();
    $m->setPosition(10.0, 20.0, 'main')->addMotion(3.0, -1.0)->addMotion(1.0, 1.0)->addWheel(0.0, -2.0)->update(MouseButton::LEFT, true);

    expect([$m->x(), $m->y(), $m->window()])->toBe([10.0, 20.0, 'main'])
        ->and($m->motion())->toBe(['dx' => 4.0, 'dy' => 0.0])
        ->and($m->wheel())->toBe(['dx' => 0.0, 'dy' => -2.0])
        ->and($m->isPressed(MouseButton::LEFT))->toBeTrue()
        ->and($m->button(MouseButton::RIGHT)->isDown())->toBeFalse();

    $m->settle();

    expect($m->motion())->toBe(['dx' => 0.0, 'dy' => 0.0])
        ->and($m->wheel())->toBe(['dx' => 0.0, 'dy' => 0.0])
        ->and($m->isDown(MouseButton::LEFT))->toBeTrue()
        ->and($m->x())->toBe(10.0);
});

it('gives a game pad only the buttons it was built with', function () {
    $pad = new GamePad('p1', 'NES', [GamepadButton::SOUTH, GamepadButton::START]);
    $pad->settle();
    $pad->update(GamepadButton::SOUTH, true)->update(GamepadButton::GUIDE, true);

    expect([$pad->id(), $pad->name()])->toBe(['p1', 'NES'])
        ->and($pad->supports(GamepadButton::GUIDE))->toBeFalse()
        ->and($pad->isDown(GamepadButton::GUIDE))->toBeFalse()
        ->and($pad->downButtons())->toBe([GamepadButton::SOUTH])
        ->and($pad->pressedButtons())->toBe([GamepadButton::SOUTH])
        ->and(fn () => $pad->button(GamepadButton::GUIDE))->toThrow(HumanInputException::class, "'p1' has no guide button.");
});

it('clamps controller axes: sticks to −1…1, triggers to 0…1', function () {
    $c = new GameController('c1', 'Pad', [GamepadButton::SOUTH], [GamepadAxis::LEFT_X, GamepadAxis::LEFT_Y, GamepadAxis::RIGHT_TRIGGER]);
    $c->setAxis(GamepadAxis::LEFT_X, 1.4)->setAxis(GamepadAxis::LEFT_Y, -3.0)->setAxis(GamepadAxis::RIGHT_TRIGGER, -0.5)->setAxis(GamepadAxis::RIGHT_X, 0.7);

    expect($c->leftStick())->toBe(['x' => 1.0, 'y' => -1.0])
        ->and($c->rightStick())->toBe(['x' => 0.0, 'y' => 0.0])
        ->and($c->rightTrigger())->toBe(0.0)
        ->and($c->axis(GamepadAxis::RIGHT_X))->toBe(0.0)
        ->and($c->axes())->toBe([GamepadAxis::LEFT_X, GamepadAxis::LEFT_Y, GamepadAxis::RIGHT_TRIGGER]);
});
