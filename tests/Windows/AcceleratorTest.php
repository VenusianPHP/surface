<?php

declare(strict_types=1);

use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\Windows\WindowException;
use Surface\Windows\Menus\Accelerator;

it('parses every modifier token in any case, then the key', function (): void {
    $keys = Accelerator::parse('primary+ctrl+ALT+Shift+meta+a');

    expect($keys->key)->toBe(Key::A)
        ->and([$keys->primary, $keys->ctrl, $keys->alt, $keys->shift, $keys->meta])->toBe([true, true, true, true, true]);
});

it('parses a key by its case name or by the character it types without Shift', function (): void {
    expect(Accelerator::parse('Primary+]')->key)->toBe(Key::RIGHT_BRACKET)
        ->and(Accelerator::parse('Primary+RIGHT_BRACKET')->key)->toBe(Key::RIGHT_BRACKET)
        ->and(Accelerator::parse('Primary+5')->key)->toBe(Key::DIGIT_5)
        ->and(Accelerator::parse('Primary+digit_5')->key)->toBe(Key::DIGIT_5);
});

it('parses each accelerator key with Primary', function (string $token, Key $key): void {
    expect(Accelerator::parse("Primary+{$token}")->key)->toBe($key);
})->with([
    'letter' => ['Z', Key::Z],
    'letter character' => ['z', Key::Z],
    'digit' => ['DIGIT_0', Key::DIGIT_0],
    'digit character' => ['9', Key::DIGIT_9],
    'minus' => ['-', Key::MINUS],
    'equals' => ['=', Key::EQUALS],
    'left bracket' => ['[', Key::LEFT_BRACKET],
    'right bracket' => [']', Key::RIGHT_BRACKET],
    'backslash' => ['\\', Key::BACKSLASH],
    'semicolon' => [';', Key::SEMICOLON],
    'apostrophe' => ["'", Key::APOSTROPHE],
    'grave' => ['`', Key::GRAVE],
    'comma' => [',', Key::COMMA],
    'period' => ['.', Key::PERIOD],
    'slash' => ['/', Key::SLASH],
    'punctuation name' => ['SEMICOLON', Key::SEMICOLON],
    'F1' => ['F1', Key::F1],
    'F12' => ['F12', Key::F12],
    'up' => ['UP', Key::UP],
    'down' => ['DOWN', Key::DOWN],
    'left' => ['LEFT', Key::LEFT],
    'right' => ['RIGHT', Key::RIGHT],
    'home' => ['HOME', Key::HOME],
    'end' => ['END', Key::END],
    'page up' => ['PAGE_UP', Key::PAGE_UP],
    'page down' => ['PAGE_DOWN', Key::PAGE_DOWN],
    'space' => ['SPACE', Key::SPACE],
    'enter' => ['ENTER', Key::ENTER],
    'tab' => ['TAB', Key::TAB],
    'escape' => ['ESCAPE', Key::ESCAPE],
    'backspace' => ['BACKSPACE', Key::BACKSPACE],
    'delete' => ['DELETE', Key::DELETE],
]);

it('parses a function key with no modifier', function (): void {
    $keys = Accelerator::parse('F5');

    expect($keys->key)->toBe(Key::F5)
        ->and([$keys->primary, $keys->ctrl, $keys->alt, $keys->shift, $keys->meta])->toBe([false, false, false, false, false]);
});

it('reads a hotkey as Primary and the key, a letter in either case', function (): void {
    expect(Accelerator::hotkey('x'))->toEqual(Accelerator::parse('Primary+X'))
        ->and(Accelerator::hotkey('X'))->toEqual(Accelerator::parse('Primary+X'))
        ->and(Accelerator::hotkey(','))->toEqual(Accelerator::parse('Primary+COMMA'));
});

it('refuses text that is not an accelerator, and names what is wrong', function (string $keys, string $message): void {
    expect(fn () => Accelerator::parse($keys))->toThrow(WindowException::class, $message);
})->with([
    'unknown token' => ['Primary+Bogus', "'Bogus'"],
    'modifier two times' => ['Primary+Primary+A', "'Primary'"],
    'no key' => ['Primary', "'Primary'"],
    'key first' => ['A+Primary', "'A+Primary'"],
    'two keys' => ['Primary+A+B', "'Primary+A+B'"],
    'insert' => ['Primary+INSERT', "'INSERT'"],
    'numpad' => ['Primary+NUMPAD_1', "'NUMPAD_1'"],
    'a modifier key' => ['Primary+LEFT_SHIFT', "'LEFT_SHIFT'"],
    'caps lock' => ['Primary+CAPS_LOCK', "'CAPS_LOCK'"],
    'letter alone' => ['A', "'A'"],
    'letter with Shift only' => ['Shift+A', "'Shift+A'"],
    'delete alone' => ['DELETE', "'DELETE'"],
    'empty token' => ['Primary++', "''"],
]);

it('refuses a hotkey that is not one accelerator character', function (string $hotkey): void {
    expect(fn () => Accelerator::hotkey($hotkey))->toThrow(WindowException::class, 'one character');
})->with(['plus' => ['+'], 'two characters' => ['qq'], 'empty' => ['']]);

it('refuses, at construction, a key that needs a modifier and has none', function (): void {
    expect(fn () => new Accelerator(Key::A))->toThrow(WindowException::class)
        ->and(fn () => new Accelerator(Key::NUMPAD_1, primary: true))->toThrow(WindowException::class)
        ->and((new Accelerator(Key::A, alt: true))->key)->toBe(Key::A);
});

it('gives the modifiers of each platform', function (): void {
    $primary = Accelerator::parse('Primary+X');
    $ctrl = Accelerator::parse('Ctrl+X');
    $meta = Accelerator::parse('Meta+X');

    expect([$primary->command(true), $primary->control(true), $primary->super(true)])->toBe([true, false, false])
        ->and([$primary->command(false), $primary->control(false), $primary->super(false)])->toBe([false, true, false])
        ->and([$ctrl->command(true), $ctrl->control(true), $ctrl->super(true)])->toBe([false, true, false])
        ->and([$ctrl->command(false), $ctrl->control(false), $ctrl->super(false)])->toBe([false, true, false])
        ->and([$meta->command(true), $meta->control(true), $meta->super(true)])->toBe([true, false, false])
        ->and([$meta->command(false), $meta->control(false), $meta->super(false)])->toBe([false, false, true]);
});

it('compares two accelerators on a platform', function (): void {
    expect(Accelerator::parse('Primary+X')->sameAs(Accelerator::parse('Ctrl+X'), false))->toBeTrue()
        ->and(Accelerator::parse('Primary+X')->sameAs(Accelerator::parse('Ctrl+X'), true))->toBeFalse()
        ->and(Accelerator::parse('Primary+X')->sameAs(Accelerator::parse('Meta+X'), true))->toBeTrue()
        ->and(Accelerator::parse('Primary+X')->sameAs(Accelerator::parse('Primary+Shift+X'), true))->toBeFalse()
        ->and(Accelerator::parse('Alt+X')->sameAs(Accelerator::parse('Alt+Y'), false))->toBeFalse();
});
