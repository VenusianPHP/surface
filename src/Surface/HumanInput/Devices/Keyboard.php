<?php

namespace Surface\HumanInput\Devices;

use Closure;
use Surface\Contracts\HumanInput\Devices\Keyboard as KeyboardContract;
use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\Modifiers;
use Surface\HumanInput\InputFrame;

class Keyboard implements KeyboardContract
{
    /** @var array<string, DigitalButton> keyed by Key->value */
    protected array $keys = [];

    protected string $text = '';

    protected Modifiers $modifiers;

    public function __construct(protected readonly InputFrame $frame)
    {
        $this->modifiers = new Modifiers();
    }

    public function update(Key $key, bool $down, ?int $at_ns = null): static
    {
        ($this->keys[$key->value] ??= new DigitalButton($this->frame, $key->value))->update($down, $at_ns);

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

    /** Focus left the window: every held key is released, with a release edge, and the modifiers go up. Text typed this frame stays. */
    public function releaseAll(?int $at_ns = null): void
    {
        foreach ($this->keys as $button) {
            $button->update(false, $at_ns);
        }

        $this->modifiers = new Modifiers();
    }

    public function isDown(Key $key): bool
    {
        $this->frame->read();

        return ($this->keys[$key->value] ?? null)?->isDown() ?? false;
    }

    public function isPressed(Key $key): bool
    {
        $this->frame->read();

        return ($this->keys[$key->value] ?? null)?->isPressed() ?? false;
    }

    public function wasReleased(Key $key): bool
    {
        $this->frame->read();

        return ($this->keys[$key->value] ?? null)?->wasReleased() ?? false;
    }

    public function isHolding(Key $key, int $hold_ms): bool
    {
        $this->frame->read();

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
        $this->frame->read();

        return $this->text;
    }

    public function modifiers(): Modifiers
    {
        $this->frame->read();

        return $this->modifiers;
    }

    /** @return list<Key> */
    private function keysWhere(Closure $test): array
    {
        $this->frame->read();

        return array_values(array_map(
            fn (DigitalButton $b): Key => Key::from($b->name),
            array_filter($this->keys, $test),
        ));
    }
}
