<?php

declare(strict_types=1);

use Surface\Contracts\HumanInput\Key;
use Surface\HumanInput\Keymaps\LinuxKeycodes;
use Surface\HumanInput\Keymaps\MacKeycodes;

/** @return array<string, int> every Key a table names, by key value, to its one code */
function keymapCodes(Closure $key, int $upTo): array
{
    $seen = [];
    for ($code = 0; $code <= $upTo; $code++) {
        $named = $key($code);
        if ($named !== Key::UNKNOWN) {
            expect($seen)->not->toHaveKey($named->value);
            $seen[$named->value] = $code;
        }
    }

    return $seen;
}

it('names every Surface key but fn from exactly one evdev code', function (): void {
    $seen = keymapCodes(LinuxKeycodes::key(...), 0x2FF);
    $missing = array_values(array_diff(array_map(fn (Key $key): string => $key->value, Key::cases()), array_keys($seen)));

    expect($missing)->toBe(['function', 'unknown']);
});

it('reads evdev positions, named as SDL names them on Linux', function (): void {
    expect([LinuxKeycodes::key(30), LinuxKeycodes::key(17), LinuxKeycodes::key(11), LinuxKeycodes::key(42), LinuxKeycodes::key(54)])
        ->toBe([Key::A, Key::W, Key::DIGIT_0, Key::LEFT_SHIFT, Key::RIGHT_SHIFT])
        ->and([LinuxKeycodes::key(99), LinuxKeycodes::key(127), LinuxKeycodes::key(355), LinuxKeycodes::key(125), LinuxKeycodes::key(96)])
        ->toBe([Key::PRINT_SCREEN, Key::MENU, Key::CLEAR, Key::LEFT_META, Key::NUMPAD_ENTER])
        ->and([LinuxKeycodes::key(86), LinuxKeycodes::key(139), LinuxKeycodes::key(0), LinuxKeycodes::key(-1)])
        ->toBe([Key::UNKNOWN, Key::UNKNOWN, Key::UNKNOWN, Key::UNKNOWN]);
});

it('names every Surface key but clear from exactly one macOS key code', function (): void {
    $seen = keymapCodes(MacKeycodes::key(...), 0x7F);
    $missing = array_values(array_diff(array_map(fn (Key $key): string => $key->value, Key::cases()), array_keys($seen)));

    expect($missing)->toBe(['clear', 'unknown']);
});

it('reads macOS positions, named as SDL names them on macOS', function (): void {
    expect([MacKeycodes::key(0x00), MacKeycodes::key(0x0D), MacKeycodes::key(0x1D), MacKeycodes::key(0x38), MacKeycodes::key(0x3F)])
        ->toBe([Key::A, Key::W, Key::DIGIT_0, Key::LEFT_SHIFT, Key::FUNCTION])
        ->and([MacKeycodes::key(0x69), MacKeycodes::key(0x6B), MacKeycodes::key(0x71), MacKeycodes::key(0x72), MacKeycodes::key(0x47), MacKeycodes::key(0x6E)])
        ->toBe([Key::PRINT_SCREEN, Key::SCROLL_LOCK, Key::PAUSE, Key::INSERT, Key::NUM_LOCK, Key::MENU])
        ->and([MacKeycodes::key(0x0A), MacKeycodes::key(0x48), MacKeycodes::key(500)])->toBe([Key::UNKNOWN, Key::UNKNOWN, Key::UNKNOWN]);
});
