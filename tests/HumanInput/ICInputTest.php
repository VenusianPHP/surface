<?php

use Surface\Contracts\HumanInput\Devices\GameController as GameControllerContract;
use Surface\Contracts\HumanInput\GamepadAxis;
use Surface\Contracts\HumanInput\GamepadButton;
use Surface\HumanInput\ICInput;
use Venusian\Surface\Tests\Support\Fakes\FakeButtonPad;
use Venusian\Surface\Tests\Support\Fakes\FakeControllerPad;

it('wraps a button pad as a game pad with only the buttons the circuit supports', function () {
    $ic = new FakeButtonPad([GamepadButton::SOUTH, GamepadButton::EAST, GamepadButton::START]);
    $input = new ICInput('p1', $ic);

    expect($input->device())->not->toBeInstanceOf(GameControllerContract::class)
        ->and($input->device()->id())->toBe('p1')
        ->and($input->device()->supports(GamepadButton::START))->toBeTrue()
        ->and($input->device()->supports(GamepadButton::GUIDE))->toBeFalse();
});

it('polls the circuit, then derives edges from what is down', function () {
    $ic = new FakeButtonPad([GamepadButton::SOUTH, GamepadButton::EAST]);
    $input = new ICInput('p1', $ic);

    $ic->down = [GamepadButton::SOUTH];
    $input->poll();
    expect($ic->polls)->toBe(1)
        ->and($input->device()->pressedButtons())->toBe([GamepadButton::SOUTH]);

    $input->poll();
    expect($input->device()->pressedButtons())->toBe([])
        ->and($input->device()->downButtons())->toBe([GamepadButton::SOUTH]);

    $ic->down = [];
    $input->poll();
    expect($input->device()->wasReleased(GamepadButton::SOUTH))->toBeTrue();
});

it('wraps a circuit with a stick as a game controller and copies its axes', function () {
    $ic = new FakeControllerPad([GamepadButton::SOUTH], [GamepadAxis::LEFT_X, GamepadAxis::LEFT_Y]);
    $input = new ICInput('stick', $ic);
    $ic->values = ['left_x' => 0.5, 'left_y' => -0.25];

    $input->poll();

    expect($input->device())->toBeInstanceOf(GameControllerContract::class)
        ->and($input->device()->leftStick())->toBe(['x' => 0.5, 'y' => -0.25]);
});

it('wraps a circuit whose only axes are triggers as a game pad', function () {
    $input = new ICInput('t', new FakeControllerPad([GamepadButton::SOUTH], [GamepadAxis::LEFT_TRIGGER]));

    expect($input->device())->not->toBeInstanceOf(GameControllerContract::class);
});

it('skips a circuit that is not connected, and releases what it held', function () {
    $ic = new FakeButtonPad([GamepadButton::SOUTH]);
    $input = new ICInput('p1', $ic);
    $ic->down = [GamepadButton::SOUTH];
    $input->poll();

    $ic->connected = false;
    $input->poll();

    expect($ic->polls)->toBe(1)
        ->and($input->connected())->toBeFalse()
        ->and($input->device()->wasReleased(GamepadButton::SOUTH))->toBeTrue();
});

it('keeps a tap the circuit saw between two polls', function () {
    $ic = new FakeButtonPad([GamepadButton::SOUTH]);
    $input = new ICInput('p1', $ic);

    $ic->pressed = [GamepadButton::SOUTH];
    $ic->released = [GamepadButton::SOUTH];
    $input->poll();

    expect($input->device()->isPressed(GamepadButton::SOUTH))->toBeTrue()
        ->and($input->device()->wasReleased(GamepadButton::SOUTH))->toBeTrue()
        ->and($input->device()->isDown(GamepadButton::SOUTH))->toBeFalse();
});

it('keeps a release and re-press the circuit saw between two polls', function () {
    $ic = new FakeButtonPad([GamepadButton::SOUTH]);
    $input = new ICInput('p1', $ic);
    $ic->down = [GamepadButton::SOUTH];
    $input->poll();

    $ic->pressed = [GamepadButton::SOUTH];
    $ic->released = [GamepadButton::SOUTH];
    $input->poll();

    expect($input->device()->wasReleased(GamepadButton::SOUTH))->toBeTrue()
        ->and($input->device()->isPressed(GamepadButton::SOUTH))->toBeTrue()
        ->and($input->device()->isDown(GamepadButton::SOUTH))->toBeTrue();
});

it('ignores a circuit release edge when the device was not down', function () {
    $ic = new FakeButtonPad([GamepadButton::SOUTH]);
    $input = new ICInput('p1', $ic);

    $ic->down = [GamepadButton::SOUTH];
    $ic->pressed = [GamepadButton::SOUTH];
    $ic->released = [GamepadButton::SOUTH];
    $input->poll();

    expect($input->device()->isPressed(GamepadButton::SOUTH))->toBeTrue()
        ->and($input->device()->wasReleased(GamepadButton::SOUTH))->toBeFalse()
        ->and($input->device()->isDown(GamepadButton::SOUTH))->toBeTrue();
});

it('faults a circuit whose poll throws: releases everything, zeroes axes, never polls it again', function () {
    $ic = new FakeControllerPad([GamepadButton::SOUTH], [GamepadAxis::LEFT_X]);
    $input = new ICInput('stick', $ic);
    $ic->down = [GamepadButton::SOUTH];
    $ic->values = ['left_x' => 0.75];
    $input->poll();

    $boom = new RuntimeException('bus gone');
    $ic->poll_failure = $boom;
    $input->poll();

    expect($input->faulted())->toBeTrue()
        ->and($input->fault())->toBe($boom)
        ->and($input->connected())->toBeFalse()
        ->and($input->device()->wasReleased(GamepadButton::SOUTH))->toBeTrue()
        ->and($input->device()->isDown(GamepadButton::SOUTH))->toBeFalse()
        ->and($input->device()->axis(GamepadAxis::LEFT_X))->toBe(0.0);

    $ic->poll_failure = null;
    $ic->down = [GamepadButton::SOUTH];
    $input->poll();

    expect($ic->polls)->toBe(2)
        ->and($input->device()->isDown(GamepadButton::SOUTH))->toBeFalse()
        ->and($input->device()->wasReleased(GamepadButton::SOUTH))->toBeFalse();
});

it('faults a circuit whose connected() throws', function () {
    $ic = new FakeButtonPad([GamepadButton::SOUTH]);
    $input = new ICInput('p1', $ic);
    $ic->connected_failure = new RuntimeException('bus gone');

    $input->poll();

    expect($input->faulted())->toBeTrue()
        ->and($ic->polls)->toBe(0);

    $ic->connected_failure = null;

    expect($input->connected())->toBeFalse();
});

it('starts unfaulted', function () {
    $input = new ICInput('p1', new FakeButtonPad([GamepadButton::SOUTH]));

    expect($input->faulted())->toBeFalse()
        ->and($input->fault())->toBeNull();
});
