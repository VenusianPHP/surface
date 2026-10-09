<?php

namespace Surface\HumanInput\Devices;

use Closure;
use Surface\Contracts\HumanInput\Devices\GameController as GameControllerContract;
use Surface\Contracts\HumanInput\GamepadAxis;
use Surface\Contracts\HumanInput\GamepadButton;
use Surface\HumanInput\InputFrame;

/** A game pad with analog axes. Values are clamped on the way in, so jitter past range never reaches a sketch; an axis it was not built with is ignored. */
class GameController extends GamePad implements GameControllerContract
{
    /** @var array<string, float> keyed by GamepadAxis->value */
    protected array $values = [];

    /**
     * @param list<GamepadButton> $buttons
     * @param list<GamepadAxis> $axes
     * @param Closure(?int): void|null $player_lights
     */
    public function __construct(InputFrame $frame, string $id, string $name, array $buttons, protected readonly array $axes, ?string $hardware_id = null, ?Closure $player_lights = null)
    {
        parent::__construct($frame, $id, $name, $buttons, $hardware_id, $player_lights);

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

    /** The pad went away or faulted: buttons released with an edge each, every axis back to rest. */
    public function releaseAll(?int $at_ns = null): void
    {
        parent::releaseAll($at_ns);

        foreach (array_keys($this->values) as $axis) {
            $this->values[$axis] = 0.0;
        }
    }

    public function axes(): array
    {
        return $this->axes;
    }

    public function axis(GamepadAxis $axis): float
    {
        $this->frame->read();

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
