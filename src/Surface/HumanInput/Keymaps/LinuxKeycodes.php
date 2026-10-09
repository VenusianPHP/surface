<?php

namespace Surface\HumanInput\Keymaps;

use Surface\Contracts\HumanInput\Key;

/**
 * Linux evdev key codes (input-event-codes.h KEY_*: keyboard positions, layout independent)
 * as Surface keys, named as SDL names them on Linux: KEY_SYSRQ is Print Screen, KEY_COMPOSE
 * (the PC menu key) is Menu, KEY_CLEAR is Clear. Linux reports no fn key, so Key::FUNCTION
 * has no code. Anything else (KEY_102ND, KEY_MENU, media keys, F13 and up) is Key::UNKNOWN.
 * A toolkit's hardware keycode on X11 and Wayland is this code + 8.
 */
final class LinuxKeycodes
{
    /** @var array<int, Key> by evdev code */
    private const array CODES = [
        1 => Key::ESCAPE, 2 => Key::DIGIT_1, 3 => Key::DIGIT_2, 4 => Key::DIGIT_3, 5 => Key::DIGIT_4,
        6 => Key::DIGIT_5, 7 => Key::DIGIT_6, 8 => Key::DIGIT_7, 9 => Key::DIGIT_8, 10 => Key::DIGIT_9,
        11 => Key::DIGIT_0, 12 => Key::MINUS, 13 => Key::EQUALS, 14 => Key::BACKSPACE, 15 => Key::TAB,
        16 => Key::Q, 17 => Key::W, 18 => Key::E, 19 => Key::R, 20 => Key::T, 21 => Key::Y, 22 => Key::U,
        23 => Key::I, 24 => Key::O, 25 => Key::P, 26 => Key::LEFT_BRACKET, 27 => Key::RIGHT_BRACKET,
        28 => Key::ENTER, 29 => Key::LEFT_CTRL, 30 => Key::A, 31 => Key::S, 32 => Key::D, 33 => Key::F,
        34 => Key::G, 35 => Key::H, 36 => Key::J, 37 => Key::K, 38 => Key::L, 39 => Key::SEMICOLON,
        40 => Key::APOSTROPHE, 41 => Key::GRAVE, 42 => Key::LEFT_SHIFT, 43 => Key::BACKSLASH, 44 => Key::Z,
        45 => Key::X, 46 => Key::C, 47 => Key::V, 48 => Key::B, 49 => Key::N, 50 => Key::M, 51 => Key::COMMA,
        52 => Key::PERIOD, 53 => Key::SLASH, 54 => Key::RIGHT_SHIFT, 55 => Key::NUMPAD_MULTIPLY,
        56 => Key::LEFT_ALT, 57 => Key::SPACE, 58 => Key::CAPS_LOCK,
        59 => Key::F1, 60 => Key::F2, 61 => Key::F3, 62 => Key::F4, 63 => Key::F5, 64 => Key::F6,
        65 => Key::F7, 66 => Key::F8, 67 => Key::F9, 68 => Key::F10,
        69 => Key::NUM_LOCK, 70 => Key::SCROLL_LOCK, 71 => Key::NUMPAD_7, 72 => Key::NUMPAD_8, 73 => Key::NUMPAD_9,
        74 => Key::NUMPAD_MINUS, 75 => Key::NUMPAD_4, 76 => Key::NUMPAD_5, 77 => Key::NUMPAD_6, 78 => Key::NUMPAD_PLUS,
        79 => Key::NUMPAD_1, 80 => Key::NUMPAD_2, 81 => Key::NUMPAD_3, 82 => Key::NUMPAD_0, 83 => Key::NUMPAD_PERIOD,
        87 => Key::F11, 88 => Key::F12, 96 => Key::NUMPAD_ENTER, 97 => Key::RIGHT_CTRL, 98 => Key::NUMPAD_DIVIDE,
        99 => Key::PRINT_SCREEN, 100 => Key::RIGHT_ALT, 102 => Key::HOME, 103 => Key::UP, 104 => Key::PAGE_UP,
        105 => Key::LEFT, 106 => Key::RIGHT, 107 => Key::END, 108 => Key::DOWN, 109 => Key::PAGE_DOWN,
        110 => Key::INSERT, 111 => Key::DELETE, 117 => Key::NUMPAD_EQUALS, 119 => Key::PAUSE,
        125 => Key::LEFT_META, 126 => Key::RIGHT_META, 127 => Key::MENU, 355 => Key::CLEAR,
    ];

    public static function key(int $code): Key
    {
        return self::CODES[$code] ?? Key::UNKNOWN;
    }
}
