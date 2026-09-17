<?php

namespace Surface\HumanInput\Devices;

use Surface\Contracts\HumanInput\ButtonState;
use Surface\Contracts\HumanInput\Devices\GamePad as GamePadContract;
use Surface\Contracts\HumanInput\GamepadButton;
use Surface\Contracts\HumanInput\HumanInputException;

/** Digital buttons only. A button the pad was not built with is ignored on update and refused on button(). */
class GamePad implements GamePadContract
{
    /** @var array<string, DigitalButton> keyed by GamepadButton->value, in the order given */
    protected array $buttons = [];

    /** @param list<GamepadButton> $buttons */
    public function __construct(
        protected readonly string $id,
        protected readonly string $name,
        array $buttons,
    ) {
        foreach ($buttons as $button) {
            $this->buttons[$button->value] = new DigitalButton($button->value);
        }
    }

    public function update(GamepadButton $button, bool $down, ?int $at_ns = null): static
    {
        ($this->buttons[$button->value] ?? null)?->update($down, $at_ns);

        return $this;
    }

    public function settle(): void
    {
        foreach ($this->buttons as $button) {
            $button->settle();
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function supports(GamepadButton $button): bool
    {
        return array_key_exists($button->value, $this->buttons);
    }

    public function button(GamepadButton $button): ButtonState
    {
        return $this->buttons[$button->value] ?? throw HumanInputException::unsupportedButton($this->id, $button);
    }

    public function isDown(GamepadButton $button): bool
    {
        return ($this->buttons[$button->value] ?? null)?->isDown() ?? false;
    }

    public function isPressed(GamepadButton $button): bool
    {
        return ($this->buttons[$button->value] ?? null)?->isPressed() ?? false;
    }

    public function wasReleased(GamepadButton $button): bool
    {
        return ($this->buttons[$button->value] ?? null)?->wasReleased() ?? false;
    }

    public function isHolding(GamepadButton $button, int $hold_ms): bool
    {
        return ($this->buttons[$button->value] ?? null)?->isHolding($hold_ms) ?? false;
    }

    public function downButtons(): array
    {
        return $this->buttonsWhere(fn (DigitalButton $b): bool => $b->isDown());
    }

    public function pressedButtons(): array
    {
        return $this->buttonsWhere(fn (DigitalButton $b): bool => $b->isPressed());
    }

    /** @return list<GamepadButton> */
    private function buttonsWhere(\Closure $test): array
    {
        return array_values(array_map(
            fn (DigitalButton $b): GamepadButton => GamepadButton::from($b->name),
            array_filter($this->buttons, $test),
        ));
    }
}
