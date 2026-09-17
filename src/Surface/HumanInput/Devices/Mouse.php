<?php

namespace Surface\HumanInput\Devices;

use Surface\Contracts\HumanInput\ButtonState;
use Surface\Contracts\HumanInput\Devices\Mouse as MouseContract;
use Surface\Contracts\HumanInput\MouseButton;

/** The pointer. Position is absolute and kept across polls; motion and wheel accumulate until settle(). */
class Mouse implements MouseContract
{
    /** @var array<string, DigitalButton> keyed by MouseButton->value */
    protected array $buttons = [];

    protected float $x = 0.0;

    protected float $y = 0.0;

    protected ?string $window = null;

    protected float $motion_dx = 0.0;

    protected float $motion_dy = 0.0;

    protected float $wheel_dx = 0.0;

    protected float $wheel_dy = 0.0;

    public function __construct()
    {
        foreach (MouseButton::cases() as $button) {
            $this->buttons[$button->value] = new DigitalButton($button->value);
        }
    }

    public function setPosition(float $x, float $y, ?string $window): static
    {
        $this->x = $x;
        $this->y = $y;
        $this->window = $window;

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

    public function x(): float
    {
        return $this->x;
    }

    public function y(): float
    {
        return $this->y;
    }

    public function window(): ?string
    {
        return $this->window;
    }

    /** @return array{dx: float, dy: float} */
    public function motion(): array
    {
        return ['dx' => $this->motion_dx, 'dy' => $this->motion_dy];
    }

    /** @return array{dx: float, dy: float} */
    public function wheel(): array
    {
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
