<?php

namespace Surface\HumanInput;

use Surface\Contracts\HumanInput\Circuits\ButtonPad;
use Surface\Contracts\HumanInput\Circuits\GameController as ControllerCircuit;
use Surface\Contracts\HumanInput\GamepadAxis;
use Surface\Contracts\HumanInput\GamepadButton;
use Surface\HumanInput\Devices\GameController;
use Surface\HumanInput\Devices\GamePad;

/**
 * One polled circuit as a Surface device. The device is a game controller when
 * the circuit has a stick, a game pad otherwise. The device follows what the
 * circuit reports down; the circuit's own edges add a tap (or a release and
 * re-press) that began and ended inside its poll. A circuit that reports
 * disconnected is not polled and reads all-released. A circuit that throws is
 * faulted: released, zeroed, reported disconnected and never polled again —
 * detach() and attach() it to recover.
 */
final class ICInput
{
    private readonly GamePad $device;

    /** @var list<GamepadButton> */
    private readonly array $buttons;

    /** @var list<GamepadAxis> */
    private readonly array $axes;

    private ?\Throwable $fault = null;

    public function __construct(public readonly string $name, private readonly ButtonPad $ic)
    {
        $this->buttons = array_values(array_filter(GamepadButton::cases(), fn (GamepadButton $b): bool => $ic->supports($b)));
        $this->axes = $ic instanceof ControllerCircuit ? $ic->supportedAxes() : [];

        $has_stick = in_array(GamepadAxis::LEFT_X, $this->axes, true) || in_array(GamepadAxis::RIGHT_X, $this->axes, true);
        $this->device = $has_stick
            ? new GameController($name, $name, $this->buttons, $this->axes)
            : new GamePad($name, $name, $this->buttons);
    }

    public function device(): GamePad
    {
        return $this->device;
    }

    public function circuit(): ButtonPad
    {
        return $this->ic;
    }

    public function connected(): bool
    {
        if ($this->faulted()) {
            return false;
        }

        try {
            return $this->ic->connected();
        } catch (\Throwable $e) {
            $this->failWith($e);

            return false;
        }
    }

    public function faulted(): bool
    {
        return ! is_null($this->fault);
    }

    public function fault(): ?\Throwable
    {
        return $this->fault;
    }

    public function poll(): void
    {
        $this->device->settle();

        if ($this->faulted()) {
            return;
        }

        try {
            $live = $this->ic->connected();

            if (! $live) {
                $this->release();

                return;
            }

            $this->ic->poll();
            $reads = [];

            foreach ($this->buttons as $button) {
                $reads[] = [$button, $this->ic->isDown($button), $this->ic->isPressed($button), $this->ic->wasReleased($button)];
            }

            $values = [];

            if ($this->ic instanceof ControllerCircuit) {
                foreach ($this->axes as $axis) {
                    $values[] = [$axis, $this->ic->axis($axis)];
                }
            }
        } catch (\Throwable $e) {
            $this->failWith($e);

            return;
        }

        foreach ($reads as [$button, $down, $pressed, $released]) {
            $this->apply($button, $down, $pressed, $released);
        }

        if ($this->device instanceof GameController) {
            foreach ($values as [$axis, $value]) {
                $this->device->setAxis($axis, $value);
            }
        }
    }

    private function apply(GamepadButton $button, bool $down, bool $pressed, bool $released): void
    {
        if ($pressed && ! $down) {
            $this->device->update($button, true)->update($button, false);

            return;
        }

        if ($released && $down && $this->device->isDown($button)) {
            $this->device->update($button, false)->update($button, true);

            return;
        }

        $this->device->update($button, $down);
    }

    private function failWith(\Throwable $e): void
    {
        $this->fault = $e;
        $this->release();
    }

    private function release(): void
    {
        foreach ($this->buttons as $button) {
            $this->device->update($button, false);
        }

        if ($this->device instanceof GameController) {
            foreach ($this->axes as $axis) {
                $this->device->setAxis($axis, 0.0);
            }
        }
    }
}
