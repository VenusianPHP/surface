<?php

namespace Surface\HumanInput\Devices;

use Surface\Contracts\HumanInput\ButtonState;
use Surface\Contracts\HumanInput\Devices\Mouse as MouseContract;
use Surface\Contracts\HumanInput\MouseButton;
use Surface\HumanInput\InputFrame;

/** The pointer. Position is absolute and kept; motion and wheel add up until settle(). */
class Mouse implements MouseContract
{
    /** Position and button reports across every mouse in the process, so mice of several engines can be ordered. */
    private static int $reports = 0;

    protected int $reported = 0;

    /** @var array<string, DigitalButton> keyed by MouseButton->value */
    protected array $buttons = [];

    protected float $x = 0.0;

    protected float $y = 0.0;

    protected ?string $window = null;

    protected float $motion_dx = 0.0;

    protected float $motion_dy = 0.0;

    protected float $wheel_dx = 0.0;

    protected float $wheel_dy = 0.0;

    public function __construct(protected readonly InputFrame $frame)
    {
        foreach (MouseButton::cases() as $button) {
            $this->buttons[$button->value] = new DigitalButton($frame, $button->value);
        }
    }

    public function setPosition(float $x, float $y, ?string $window): static
    {
        $this->x = $x;
        $this->y = $y;
        $this->window = $window;
        $this->reported = ++self::$reports;

        return $this;
    }

    public function addMotion(float $dx, float $dy): static
    {
        $this->motion_dx += $dx;
        $this->motion_dy += $dy;

        return $this;
    }

    public function addWheel(float $dx, float $dy): static
    {
        $this->wheel_dx += $dx;
        $this->wheel_dy += $dy;

        return $this;
    }

    public function update(MouseButton $button, bool $down, ?int $at_ns = null): static
    {
        $this->buttons[$button->value]->update($down, $at_ns);
        $this->reported = ++self::$reports;

        return $this;
    }

    public function settle(): void
    {
        $this->motion_dx = $this->motion_dy = 0.0;
        $this->wheel_dx = $this->wheel_dy = 0.0;

        foreach ($this->buttons as $button) {
            $button->settle();
        }
    }

    /** Focus left the window: every held button is released, with a release edge. */
    public function releaseAll(?int $at_ns = null): void
    {
        foreach ($this->buttons as $button) {
            $button->update(false, $at_ns);
        }
    }

    /** The order of this mouse's last position or button report among every mouse in the process; 0 before any. */
    public function lastReport(): int
    {
        return $this->reported;
    }

    public function x(): float
    {
        $this->frame->read();

        return $this->x;
    }

    public function y(): float
    {
        $this->frame->read();

        return $this->y;
    }

    public function window(): ?string
    {
        $this->frame->read();

        return $this->window;
    }

    /** @return array{dx: float, dy: float} */
    public function motion(): array
    {
        $this->frame->read();

        return ['dx' => $this->motion_dx, 'dy' => $this->motion_dy];
    }

    /** @return array{dx: float, dy: float} */
    public function wheel(): array
    {
        $this->frame->read();

        return ['dx' => $this->wheel_dx, 'dy' => $this->wheel_dy];
    }

    public function button(MouseButton $button): ButtonState
    {
        return $this->buttons[$button->value];
    }

    public function isDown(MouseButton $button): bool
    {
        return $this->buttons[$button->value]->isDown();
    }

    public function isPressed(MouseButton $button): bool
    {
        return $this->buttons[$button->value]->isPressed();
    }

    public function wasReleased(MouseButton $button): bool
    {
        return $this->buttons[$button->value]->wasReleased();
    }
}
