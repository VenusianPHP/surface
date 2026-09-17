<?php

use Surface\Contracts\Core\SurfaceLevelException;
use Surface\Contracts\HumanInput\Events\GamepadConnected;
use Surface\Contracts\HumanInput\Events\GamepadDisconnected;
use Surface\Contracts\HumanInput\GamepadAxis;
use Surface\Contracts\HumanInput\GamepadButton;
use Surface\Contracts\HumanInput\HumanInputException;
use Surface\Contracts\HumanInput\InputEngine;
use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\Modifiers;
use Surface\Contracts\HumanInput\MouseButton;
use Voyager\Contracts\IOPools\Occurrence;

it('backs every vocabulary case with its lowercase name', function (string $enum) {
    foreach ($enum::cases() as $case) {
        expect($case->value)->toBe(strtolower($case->name));
    }
})->with([Key::class, MouseButton::class, GamepadButton::class, GamepadAxis::class, InputEngine::class]);

it('carries the keys a game and a text field need', function () {
    expect(Key::cases())->toHaveCount(108)
        ->and(Key::from('space'))->toBe(Key::SPACE)
        ->and(Key::from('digit_0'))->toBe(Key::DIGIT_0)
        ->and(Key::from('numpad_enter'))->toBe(Key::NUMPAD_ENTER)
        ->and(Key::from('unknown'))->toBe(Key::UNKNOWN);
});

it('lays gamepad buttons out positionally, SDL order', function () {
    expect(array_map(fn (GamepadButton $b) => $b->value, GamepadButton::cases()))->toBe([
        'south', 'east', 'west', 'north', 'back', 'guide', 'start', 'left_stick', 'right_stick',
        'left_shoulder', 'right_shoulder', 'dpad_up', 'dpad_down', 'dpad_left', 'dpad_right',
    ])->and(array_map(fn (GamepadAxis $a) => $a->value, GamepadAxis::cases()))->toBe([
        'left_x', 'left_y', 'right_x', 'right_y', 'left_trigger', 'right_trigger',
    ]);
});

it('names hot-plug mail by device id', function () {
    $on = new GamepadConnected('sdl3-4', 'Xbox Wireless Controller');
    $off = new GamepadDisconnected('sdl3-4');

    expect($on)->toBeInstanceOf(Occurrence::class)
        ->and($on->name)->toBe('input.gamepad.connected.sdl3-4')
        ->and($on->device_name)->toBe('Xbox Wireless Controller')
        ->and($off->name)->toBe('input.gamepad.disconnected.sdl3-4');
});

it('reports failures as one Surface type', function () {
    expect(HumanInputException::nameTaken('p1'))->toBeInstanceOf(SurfaceLevelException::class)
        ->and(HumanInputException::nameTaken('p1')->getMessage())->toBe("Input circuit 'p1' is already attached.")
        ->and(HumanInputException::noSuchCircuit('p2')->getMessage())->toBe("No input circuit named 'p2'.")
        ->and(HumanInputException::unsupportedButton('p1', GamepadButton::GUIDE)->getMessage())->toBe("'p1' has no guide button.")
        ->and(HumanInputException::unsupportedAxis('p1', GamepadAxis::RIGHT_X)->getMessage())->toBe("'p1' has no right_x axis.")
        ->and(HumanInputException::notConnected(InputEngine::GTK)->getMessage())->toBe("The 'gtk' input engine is not connected.");
});

it('defaults modifiers to all up', function () {
    $m = new Modifiers();

    expect([$m->shift, $m->ctrl, $m->alt, $m->meta])->toBe([false, false, false, false]);
});
