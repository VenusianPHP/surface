<?php

namespace Surface\HumanInput\Devices;

use Surface\Contracts\HumanInput\Devices\Keyboard as KeyboardContract;
use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\Modifiers;

class Keyboard implements KeyboardContract
{
    /** @var array<string, DigitalButton> keyed by Key->value */
    protected array $keys = [];

    protected string $text = '';

    protected Modifiers $modifiers;

    public function __construct()
    {
        $this->modifiers = new Modifiers();
    }

    public function update(Key $key, bool $down, ?int $at_ns = null): static
    {
        ($this->keys[$key->value] ??= new DigitalButton($key->value))->update($down, $at_ns);

        return $this;
    }

    public function appendText(string $text): static
    {
        $this->text .= $text;

        return $this;
    }

    public function setModifiers(Modifiers $modifiers): static
    {
        $this->modifiers = $modifiers;

        return $this;
    }

    public function settle(): void
    {
        $this->text = '';

        foreach ($this->keys as $button) {
            $button->settle();
        }
    }

    public function reset(): void
    {
        $this->text = '';
        $this->modifiers = new Modifiers();

        foreach ($this->keys as $button) {
            $button->reset();
        }
    }

    public function isDown(Key $key): bool
    {
        return ($this->keys[$key->value] ?? null)?->isDown() ?? false;
    }

    public function isPressed(Key $key): bool
    {
        return ($this->keys[$key->value] ?? null)?->isPressed() ?? false;
    }

    public function wasReleased(Key $key): bool
    {
        return ($this->keys[$key->value] ?? null)?->wasReleased() ?? false;
    }

    public function isHolding(Key $key, int $hold_ms): bool
    {
        return ($this->keys[$key->value] ?? null)?->isHolding($hold_ms) ?? false;
    }

    public function downKeys(): array
    {
        return $this->keysWhere(fn (DigitalButton $b): bool => $b->isDown());
    }

    public function pressedKeys(): array
    {
        return $this->keysWhere(fn (DigitalButton $b): bool => $b->isPressed());
    }

    public function releasedKeys(): array
    {
        return $this->keysWhere(fn (DigitalButton $b): bool => $b->wasReleased());
    }

    public function text(): string
    {
        return $this->text;
    }

    public function modifiers(): Modifiers
    {
        return $this->modifiers;
    }

    /** @return list<Key> */
    private function keysWhere(\Closure $test): array
    {
        return array_values(array_map(
            fn (DigitalButton $b): Key => Key::from($b->name),
            array_filter($this->keys, $test),
        ));
    }
}
