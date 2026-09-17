<?php

namespace Surface\HumanInput\Devices;

use Surface\Contracts\HumanInput\Devices\GameController as GameControllerContract;
use Surface\Contracts\HumanInput\GamepadAxis;

/** A game pad with analog axes. Values are clamped on the way in, so jitter past range never reaches a sketch. */
class GameController extends GamePad implements GameControllerContract
{
    /** @var array<string, float> keyed by GamepadAxis->value */
    protected array $values = [];

    /**
     * @param list<\Surface\Contracts\HumanInput\GamepadButton> $buttons
     * @param list<GamepadAxis> $axes
     */
    public function __construct(string $id, string $name, array $buttons, protected readonly array $axes)
    {
        parent::__construct($id, $name, $buttons);

        foreach ($axes as $axis) {
            $this->values[$axis->value] = 0.0;
        }
    }

    public function setAxis(GamepadAxis $axis, float $value): static
    {
        if (array_key_exists($axis->value, $this->values)) {
            $floor = in_array($axis, [GamepadAxis::LEFT_TRIGGER, GamepadAxis::RIGHT_TRIGGER], true) ? 0.0 : -1.0;
            $this->values[$axis->value] = max($floor, min(1.0, $value));
        }

        return $this;
    }

    public function axes(): array
    {
        return $this->axes;
    }

    public function axis(GamepadAxis $axis): float
    {
        return $this->values[$axis->value] ?? 0.0;
    }

    public function leftStick(): array
    {
        return ['x' => $this->axis(GamepadAxis::LEFT_X), 'y' => $this->axis(GamepadAxis::LEFT_Y)];
    }

    public function rightStick(): array
    {
        return ['x' => $this->axis(GamepadAxis::RIGHT_X), 'y' => $this->axis(GamepadAxis::RIGHT_Y)];
    }

    public function leftTrigger(): float
    {
        return $this->axis(GamepadAxis::LEFT_TRIGGER);
    }

    public function rightTrigger(): float
    {
        return $this->axis(GamepadAxis::RIGHT_TRIGGER);
    }
}
