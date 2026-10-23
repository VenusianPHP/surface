<?php

namespace Surface\Windows\Menus;

use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\Windows\WindowException;

/**
 * A key combination that chooses a menu item: one key and its modifiers. Primary is Command on
 * macOS and Control on other platforms; Meta is Command on macOS and Super on other platforms.
 * Only keys that a menu can carry on AppKit, GTK and Qt are accelerator keys. A key other than
 * F1-F12 needs Primary, Ctrl, Alt or Meta, so an accelerator never takes typed text.
 */
final readonly class Accelerator
{
    /** The accelerator keys by case name. */
    private const array KEYS = [
        'A' => Key::A, 'B' => Key::B, 'C' => Key::C, 'D' => Key::D, 'E' => Key::E, 'F' => Key::F,
        'G' => Key::G, 'H' => Key::H, 'I' => Key::I, 'J' => Key::J, 'K' => Key::K, 'L' => Key::L,
        'M' => Key::M, 'N' => Key::N, 'O' => Key::O, 'P' => Key::P, 'Q' => Key::Q, 'R' => Key::R,
        'S' => Key::S, 'T' => Key::T, 'U' => Key::U, 'V' => Key::V, 'W' => Key::W, 'X' => Key::X,
        'Y' => Key::Y, 'Z' => Key::Z,
        'DIGIT_0' => Key::DIGIT_0, 'DIGIT_1' => Key::DIGIT_1, 'DIGIT_2' => Key::DIGIT_2, 'DIGIT_3' => Key::DIGIT_3,
        'DIGIT_4' => Key::DIGIT_4, 'DIGIT_5' => Key::DIGIT_5, 'DIGIT_6' => Key::DIGIT_6, 'DIGIT_7' => Key::DIGIT_7,
        'DIGIT_8' => Key::DIGIT_8, 'DIGIT_9' => Key::DIGIT_9,
        'MINUS' => Key::MINUS, 'EQUALS' => Key::EQUALS, 'LEFT_BRACKET' => Key::LEFT_BRACKET,
        'RIGHT_BRACKET' => Key::RIGHT_BRACKET, 'BACKSLASH' => Key::BACKSLASH, 'SEMICOLON' => Key::SEMICOLON,
        'APOSTROPHE' => Key::APOSTROPHE, 'GRAVE' => Key::GRAVE, 'COMMA' => Key::COMMA, 'PERIOD' => Key::PERIOD,
        'SLASH' => Key::SLASH,
        'F1' => Key::F1, 'F2' => Key::F2, 'F3' => Key::F3, 'F4' => Key::F4, 'F5' => Key::F5, 'F6' => Key::F6,
        'F7' => Key::F7, 'F8' => Key::F8, 'F9' => Key::F9, 'F10' => Key::F10, 'F11' => Key::F11, 'F12' => Key::F12,
        'UP' => Key::UP, 'DOWN' => Key::DOWN, 'LEFT' => Key::LEFT, 'RIGHT' => Key::RIGHT, 'HOME' => Key::HOME,
        'END' => Key::END, 'PAGE_UP' => Key::PAGE_UP, 'PAGE_DOWN' => Key::PAGE_DOWN,
        'SPACE' => Key::SPACE, 'ENTER' => Key::ENTER, 'TAB' => Key::TAB, 'ESCAPE' => Key::ESCAPE,
        'BACKSPACE' => Key::BACKSPACE, 'DELETE' => Key::DELETE,
    ];

    /** The character each key types without Shift, for the keys that type one. */
    public const array CHARACTERS = [
        'a' => Key::A, 'b' => Key::B, 'c' => Key::C, 'd' => Key::D, 'e' => Key::E, 'f' => Key::F,
        'g' => Key::G, 'h' => Key::H, 'i' => Key::I, 'j' => Key::J, 'k' => Key::K, 'l' => Key::L,
        'm' => Key::M, 'n' => Key::N, 'o' => Key::O, 'p' => Key::P, 'q' => Key::Q, 'r' => Key::R,
        's' => Key::S, 't' => Key::T, 'u' => Key::U, 'v' => Key::V, 'w' => Key::W, 'x' => Key::X,
        'y' => Key::Y, 'z' => Key::Z,
        '0' => Key::DIGIT_0, '1' => Key::DIGIT_1, '2' => Key::DIGIT_2, '3' => Key::DIGIT_3, '4' => Key::DIGIT_4,
        '5' => Key::DIGIT_5, '6' => Key::DIGIT_6, '7' => Key::DIGIT_7, '8' => Key::DIGIT_8, '9' => Key::DIGIT_9,
        '-' => Key::MINUS, '=' => Key::EQUALS, '[' => Key::LEFT_BRACKET, ']' => Key::RIGHT_BRACKET,
        '\\' => Key::BACKSLASH, ';' => Key::SEMICOLON, "'" => Key::APOSTROPHE, '`' => Key::GRAVE,
        ',' => Key::COMMA, '.' => Key::PERIOD, '/' => Key::SLASH,
    ];

    private const array MODIFIERS = ['PRIMARY' => 'primary', 'CTRL' => 'ctrl', 'ALT' => 'alt', 'SHIFT' => 'shift', 'META' => 'meta'];

    /**
     * @throws WindowException when the key is not an accelerator key, or the key needs a modifier and has none
     */
    public function __construct(
        public Key $key,
        public bool $primary = false,
        public bool $ctrl = false,
        public bool $alt = false,
        public bool $shift = false,
        public bool $meta = false,
    ) {
        if (! in_array($key, self::KEYS, true)) {
            throw new WindowException("The key '{$key->name}' is not a menu accelerator key.");
        }
        if (! $primary && ! $ctrl && ! $alt && ! $meta && ! self::stands($key)) {
            throw new WindowException("The key '{$key->name}' needs Primary, Ctrl, Alt or Meta in a menu accelerator.");
        }
    }

    /**
     * 'Primary+Shift+F5': modifier tokens, then one key token, joined by '+'. Case is not important.
     *
     * @throws WindowException
     */
    public static function parse(string $keys): self
    {
        $tokens = explode('+', $keys);
        $key = array_pop($tokens);
        $on = [];
        foreach ($tokens as $token) {
            $modifier = self::MODIFIERS[strtoupper($token)] ?? null;
            if (is_null($modifier)) {
                throw new WindowException(self::isKey($token)
                    ? "Menu accelerator '{$keys}' has a key token before its last token."
                    : "Menu accelerator '{$keys}' has an unknown token '{$token}'.");
            }
            if (isset($on[$modifier])) {
                throw new WindowException("Menu accelerator '{$keys}' has the modifier '{$token}' two times.");
            }
            $on[$modifier] = true;
        }

        $found = self::key($key);
        if (is_null($found)) {
            throw new WindowException(match (true) {
                isset(self::MODIFIERS[strtoupper($key)]) => "Menu accelerator '{$keys}' has no key token.",
                defined(Key::class.'::'.strtoupper($key)) => "Menu accelerator '{$keys}': the key '".strtoupper($key)."' is not a menu accelerator key.",
                default => "Menu accelerator '{$keys}' has an unknown token '{$key}'.",
            });
        }
        if (! ($on['primary'] ?? false) && ! ($on['ctrl'] ?? false) && ! ($on['alt'] ?? false) && ! ($on['meta'] ?? false) && ! self::stands($found)) {
            throw new WindowException("Menu accelerator '{$keys}' needs Primary, Ctrl, Alt or Meta: only F1-F12 stand alone.");
        }

        return new self($found, ...$on);
    }

    /**
     * A hotkey: one character from CHARACTERS, or a letter in upper case. 'x' gives Primary+X.
     *
     * @throws WindowException
     */
    public static function hotkey(string $hotkey): self
    {
        $key = strlen($hotkey) === 1 ? self::CHARACTERS[strtolower($hotkey)] ?? null : null;
        if (is_null($key)) {
            throw new WindowException("The hotkey '{$hotkey}' must be one character that a key types without Shift.");
        }

        return new self($key, primary: true);
    }

    /** macOS: Primary or Meta. Other platforms: false. */
    public function command(bool $macos): bool
    {
        return $macos && ($this->primary || $this->meta);
    }

    /** macOS: Ctrl. Other platforms: Primary or Ctrl. */
    public function control(bool $macos): bool
    {
        return $this->ctrl || (! $macos && $this->primary);
    }

    /** macOS: false. Other platforms: Meta. */
    public function super(bool $macos): bool
    {
        return ! $macos && $this->meta;
    }

    /** Whether the two accelerators are the same key and the same modifiers on the platform. */
    public function sameAs(Accelerator $other, bool $macos): bool
    {
        return $this->key === $other->key && $this->alt === $other->alt && $this->shift === $other->shift
            && $this->command($macos) === $other->command($macos)
            && $this->control($macos) === $other->control($macos)
            && $this->super($macos) === $other->super($macos);
    }

    private static function key(string $token): ?Key
    {
        return self::KEYS[strtoupper($token)] ?? self::CHARACTERS[strtolower($token)] ?? null;
    }

    private static function isKey(string $token): bool
    {
        return ! is_null(self::key($token)) || defined(Key::class.'::'.strtoupper($token));
    }

    /** F1-F12: the keys that need no modifier. */
    private static function stands(Key $key): bool
    {
        return in_array($key, [Key::F1, Key::F2, Key::F3, Key::F4, Key::F5, Key::F6, Key::F7, Key::F8, Key::F9, Key::F10, Key::F11, Key::F12], true);
    }
}
