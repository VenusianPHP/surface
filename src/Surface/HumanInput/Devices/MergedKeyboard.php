<?php

namespace Surface\HumanInput\Devices;

use Closure;
use Surface\Contracts\HumanInput\Devices\Keyboard as KeyboardContract;
use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\Modifiers;
use Surface\HumanInput\InputFrame;

/**
 * Every running engine's keyboard as one. A key is down, pressed, released or held when
 * any engine has it so; key lists are the union; text is the engines' text in start
 * order; modifiers are ORed. Over one engine it reads as that engine.
 */
final class MergedKeyboard implements KeyboardContract
{
    /** @param Closure(): list<KeyboardContract> $keyboards the running engines' keyboards, in start order */
    public function __construct(private readonly InputFrame $frame, private readonly Closure $keyboards) {}

    public function isDown(Key $key): bool
    {
        return $this->any(fn (KeyboardContract $k): bool => $k->isDown($key));
    }

    public function isPressed(Key $key): bool
    {
        return $this->any(fn (KeyboardContract $k): bool => $k->isPressed($key));
    }

    public function wasReleased(Key $key): bool
    {
        return $this->any(fn (KeyboardContract $k): bool => $k->wasReleased($key));
    }

    public function isHolding(Key $key, int $hold_ms): bool
    {
        return $this->any(fn (KeyboardContract $k): bool => $k->isHolding($key, $hold_ms));
    }

    public function downKeys(): array
    {
        return $this->union(fn (KeyboardContract $k): array => $k->downKeys());
    }

    public function pressedKeys(): array
    {
        return $this->union(fn (KeyboardContract $k): array => $k->pressedKeys());
    }

    public function releasedKeys(): array
    {
        return $this->union(fn (KeyboardContract $k): array => $k->releasedKeys());
    }

    public function text(): string
    {
        return implode('', array_map(fn (KeyboardContract $k): string => $k->text(), $this->keyboards()));
    }

    public function modifiers(): Modifiers
    {
        $shift = $ctrl = $alt = $meta = false;

        foreach ($this->keyboards() as $keyboard) {
            $held = $keyboard->modifiers();
            $shift = $shift || $held->shift;
            $ctrl = $ctrl || $held->ctrl;
            $alt = $alt || $held->alt;
            $meta = $meta || $held->meta;
        }

        return new Modifiers($shift, $ctrl, $alt, $meta);
    }

    /** @return list<KeyboardContract> after the frame caught up */
    private function keyboards(): array
    {
        $this->frame->read();

        return ($this->keyboards)();
    }

    private function any(Closure $test): bool
    {
        foreach ($this->keyboards() as $keyboard) {
            if ($test($keyboard)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<Key> each key once, in first-seen order */
    private function union(Closure $list): array
    {
        $keys = [];

        foreach ($this->keyboards() as $keyboard) {
            foreach ($list($keyboard) as $key) {
                $keys[$key->value] = $key;
            }
        }

        return array_values($keys);
    }
}
