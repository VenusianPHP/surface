<?php

declare(strict_types=1);

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

it('holds an edge until settled', function (): void {
    $b = new DigitalButton(inputFrame(), 'a');

    $b->settle(); $b->update(true, 1_000_000);
    expect([$b->isDown(), $b->isPressed(), $b->wasReleased()])->toBe([true, true, false]);

    $b->settle(); $b->update(true, 2_000_000);
    expect([$b->isDown(), $b->isPressed()])->toBe([true, false]);

    $b->settle(); $b->update(false, 3_000_000);
    expect([$b->isDown(), $b->wasReleased()])->toBe([false, true]);

    $b->settle();
    expect($b->wasReleased())->toBeFalse();
});

it('keeps both edges of a tap that starts and ends before a settle, and every edge until one', function (): void {
    $tap = new DigitalButton(inputFrame(), 'a');
    $tap->update(true, 1);
    $tap->update(false, 2);

    $again = new DigitalButton(inputFrame(), 'b');
    $again->update(true, 1);
    $again->update(false, 2);
    $again->update(true, 3);

    expect([$tap->isDown(), $tap->isPressed(), $tap->wasReleased()])->toBe([false, true, true])
        ->and([$again->isDown(), $again->isPressed(), $again->wasReleased()])->toBe([true, true, true]);

    $again->settle();

    expect([$again->isPressed(), $again->wasReleased()])->toBe([false, false]);
});

it('measures a hold from the press', function (): void {
    $b = new DigitalButton(inputFrame(), 'a');
    $b->update(true, hrtime(true) - 30_000_000);

    expect($b->heldMs())->toBeGreaterThanOrEqual(30)
        ->and($b->isHolding(20))->toBeTrue()
        ->and($b->isHolding(60_000))->toBeFalse();

    $b->update(false);

    expect($b->heldMs())->toBe(0);
});

it('tracks keys, text and modifiers, and forgets text on settle', function (): void {
    $kb = new Keyboard(inputFrame());
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

it('releases every held key and button on releaseAll, with a release edge each', function (): void {
    $frame = inputFrame();
    $kb = (new Keyboard($frame))->update(Key::A, true)->setModifiers(new Modifiers(shift: true));
    $mouse = (new Mouse($frame))->update(MouseButton::LEFT, true);
    $pad = (new GameController($frame, 'c1', 'Pad', [GamepadButton::SOUTH], [GamepadAxis::LEFT_X]))
        ->update(GamepadButton::SOUTH, true)
        ->setAxis(GamepadAxis::LEFT_X, 0.5);
    $kb->settle();
    $mouse->settle();
    $pad->settle();

    $kb->releaseAll();
    $mouse->releaseAll();
    $pad->releaseAll();

    expect($kb->downKeys())->toBe([])
        ->and($kb->releasedKeys())->toBe([Key::A])
        ->and($kb->modifiers())->toEqual(new Modifiers())
        ->and($mouse->isDown(MouseButton::LEFT))->toBeFalse()
        ->and($mouse->wasReleased(MouseButton::LEFT))->toBeTrue()
        ->and($pad->wasReleased(GamepadButton::SOUTH))->toBeTrue()
        ->and($pad->axis(GamepadAxis::LEFT_X))->toBe(0.0);
});

it('tracks the pointer, adding up motion and wheel until settled', function (): void {
    $m = new Mouse(inputFrame());
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

it('orders mice by their last position or button report', function (): void {
    $frame = inputFrame();
    $fresh = new Mouse($frame);
    $moved = (new Mouse($frame))->setPosition(1.0, 1.0, null);
    $clicked = (new Mouse($frame))->update(MouseButton::LEFT, true);
    $wheeled = (new Mouse($frame))->addWheel(0.0, 1.0)->addMotion(1.0, 1.0);

    expect($fresh->lastReport())->toBe(0)
        ->and($wheeled->lastReport())->toBe(0)
        ->and($clicked->lastReport())->toBeGreaterThan($moved->lastReport())
        ->and($moved->lastReport())->toBeGreaterThan(0);
});

it('gives a game pad only the buttons it was built with', function (): void {
    $pad = new GamePad(inputFrame(), 'p1', 'NES', [GamepadButton::SOUTH, GamepadButton::START]);
    $pad->update(GamepadButton::SOUTH, true)->update(GamepadButton::GUIDE, true);

    expect([$pad->id(), $pad->name()])->toBe(['p1', 'NES'])
        ->and($pad->supports(GamepadButton::GUIDE))->toBeFalse()
        ->and($pad->isDown(GamepadButton::GUIDE))->toBeFalse()
        ->and($pad->downButtons())->toBe([GamepadButton::SOUTH])
        ->and($pad->pressedButtons())->toBe([GamepadButton::SOUTH])
        ->and(fn () => $pad->button(GamepadButton::GUIDE))->toThrow(HumanInputException::class, "'p1' has no guide button.");
});

it('clamps controller axes: sticks to −1…1, triggers to 0…1, and ignores axes it was not built with', function (): void {
    $c = new GameController(inputFrame(), 'c1', 'Pad', [GamepadButton::SOUTH], [GamepadAxis::LEFT_X, GamepadAxis::LEFT_Y, GamepadAxis::RIGHT_TRIGGER]);
    $c->setAxis(GamepadAxis::LEFT_X, 1.4)->setAxis(GamepadAxis::LEFT_Y, -3.0)->setAxis(GamepadAxis::RIGHT_TRIGGER, -0.5)->setAxis(GamepadAxis::RIGHT_X, 0.7);

    expect($c->leftStick())->toBe(['x' => 1.0, 'y' => -1.0])
        ->and($c->rightStick())->toBe(['x' => 0.0, 'y' => 0.0])
        ->and($c->rightTrigger())->toBe(0.0)
        ->and($c->axis(GamepadAxis::RIGHT_X))->toBe(0.0)
        ->and($c->axes())->toBe([GamepadAxis::LEFT_X, GamepadAxis::LEFT_Y, GamepadAxis::RIGHT_TRIGGER]);
});

it('tells the frame when its state is read', function (Closure $read): void {
    $frame = inputFrame();
    $kb = (new Keyboard($frame))->update(Key::A, true);
    $mouse = new Mouse($frame);
    $pad = new GameController($frame, 'c1', 'Pad', [GamepadButton::SOUTH], [GamepadAxis::LEFT_X]);

    $read($kb, $mouse, $pad);

    expect($frame->spent())->toBeTrue();
})->with([
    'key down' => [fn (Keyboard $k) => $k->isDown(Key::B)],
    'key pressed' => [fn (Keyboard $k) => $k->isPressed(Key::B)],
    'key released' => [fn (Keyboard $k) => $k->wasReleased(Key::B)],
    'key held' => [fn (Keyboard $k) => $k->isHolding(Key::B, 1)],
    'down keys' => [fn (Keyboard $k) => $k->downKeys()],
    'pressed keys' => [fn (Keyboard $k) => $k->pressedKeys()],
    'released keys' => [fn (Keyboard $k) => $k->releasedKeys()],
    'text' => [fn (Keyboard $k) => $k->text()],
    'modifiers' => [fn (Keyboard $k) => $k->modifiers()],
    'pointer x' => [fn (Keyboard $k, Mouse $m) => $m->x()],
    'pointer y' => [fn (Keyboard $k, Mouse $m) => $m->y()],
    'window' => [fn (Keyboard $k, Mouse $m) => $m->window()],
    'motion' => [fn (Keyboard $k, Mouse $m) => $m->motion()],
    'wheel' => [fn (Keyboard $k, Mouse $m) => $m->wheel()],
    'mouse down' => [fn (Keyboard $k, Mouse $m) => $m->isDown(MouseButton::LEFT)],
    'mouse pressed' => [fn (Keyboard $k, Mouse $m) => $m->isPressed(MouseButton::LEFT)],
    'mouse released' => [fn (Keyboard $k, Mouse $m) => $m->wasReleased(MouseButton::LEFT)],
    'mouse button state' => [fn (Keyboard $k, Mouse $m) => $m->button(MouseButton::LEFT)->heldMs()],
    'pad down' => [fn (Keyboard $k, Mouse $m, GameController $c) => $c->isDown(GamepadButton::SOUTH)],
    'pad pressed' => [fn (Keyboard $k, Mouse $m, GameController $c) => $c->isPressed(GamepadButton::SOUTH)],
    'pad released' => [fn (Keyboard $k, Mouse $m, GameController $c) => $c->wasReleased(GamepadButton::SOUTH)],
    'pad held' => [fn (Keyboard $k, Mouse $m, GameController $c) => $c->isHolding(GamepadButton::SOUTH, 1)],
    'down buttons' => [fn (Keyboard $k, Mouse $m, GameController $c) => $c->downButtons()],
    'pressed buttons' => [fn (Keyboard $k, Mouse $m, GameController $c) => $c->pressedButtons()],
    'pad button state' => [fn (Keyboard $k, Mouse $m, GameController $c) => $c->button(GamepadButton::SOUTH)->isDown()],
    'axis' => [fn (Keyboard $k, Mouse $m, GameController $c) => $c->axis(GamepadAxis::LEFT_X)],
    'left stick' => [fn (Keyboard $k, Mouse $m, GameController $c) => $c->leftStick()],
    'right stick' => [fn (Keyboard $k, Mouse $m, GameController $c) => $c->rightStick()],
    'left trigger' => [fn (Keyboard $k, Mouse $m, GameController $c) => $c->leftTrigger()],
    'right trigger' => [fn (Keyboard $k, Mouse $m, GameController $c) => $c->rightTrigger()],
]);

it('leaves the frame unread by what a device is, and by updates', function (): void {
    $frame = inputFrame();
    $kb = new Keyboard($frame);
    $mouse = new Mouse($frame);
    $pad = new GameController($frame, 'c1', 'Pad', [GamepadButton::SOUTH], [GamepadAxis::LEFT_X]);

    $kb->update(Key::A, true)->appendText('a')->setModifiers(new Modifiers(alt: true));
    $mouse->setPosition(1.0, 2.0, 'w')->addMotion(1.0, 1.0)->addWheel(0.0, 1.0)->update(MouseButton::LEFT, true);
    $pad->update(GamepadButton::SOUTH, true)->setAxis(GamepadAxis::LEFT_X, 0.5);
    $kb->settle();
    $mouse->settle();
    $pad->settle();
    $kb->releaseAll();
    $mouse->releaseAll();
    $pad->releaseAll();
    $pad->id();
    $pad->name();
    $pad->supports(GamepadButton::SOUTH);
    $pad->axes();
    $pad->button(GamepadButton::SOUTH);
    $mouse->button(MouseButton::LEFT);
    $mouse->lastReport();

    expect($frame->spent())->toBeFalse();
});

it('carries the hardware id and player lights its source gave it', function (): void {
    $lit = [];
    $pad = new GamePad(inputFrame(), 'sdl3-4', 'DualSense', [GamepadButton::SOUTH], 'a0:5a:5c:12:34:56', function (?int $index) use (&$lit): void {
        $lit[] = $index;
    });
    $controller = new GameController(inputFrame(), 'gc-1', 'Xbox', [GamepadButton::SOUTH], [GamepadAxis::LEFT_X], 'xbox-serial', function (?int $index) use (&$lit): void {
        $lit[] = "c{$index}";
    });

    $pad->setPlayerIndex(1);
    $pad->setPlayerIndex(null);
    $controller->setPlayerIndex(0);

    expect($pad->hardwareId())->toBe('a0:5a:5c:12:34:56')
        ->and($controller->hardwareId())->toBe('xbox-serial')
        ->and($lit)->toBe([1, null, 'c0']);
});

it('has no hardware id and ignores player lights when its source gave neither', function (): void {
    $pad = new GamePad(inputFrame(), 'p1', 'NES', [GamepadButton::SOUTH]);

    $pad->setPlayerIndex(2);

    expect($pad->hardwareId())->toBeNull();
});
