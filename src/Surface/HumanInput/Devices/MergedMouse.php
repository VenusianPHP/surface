<?php

namespace Surface\HumanInput\Devices;

use Closure;
use Surface\Contracts\HumanInput\ButtonState;
use Surface\Contracts\HumanInput\Devices\Mouse as MouseContract;
use Surface\Contracts\HumanInput\MouseButton;
use Surface\HumanInput\InputFrame;

/**
 * Every running engine's mouse as one. The pointer is over one window at a time, so
 * position, window and buttons come from the mouse that reported a position or button
 * last; motion and wheel are the sum. With no engine it rests at the origin over no
 * window with nothing down.
 */
final class MergedMouse implements MouseContract
{
    private readonly Mouse $resting;

    /** @param Closure(): list<MouseContract> $mice the running engines' mice, in start order */
    public function __construct(private readonly InputFrame $frame, private readonly Closure $mice)
    {
        $this->resting = new Mouse($frame);
    }

    public function x(): float
    {
        return $this->current()->x();
    }

    public function y(): float
    {
        return $this->current()->y();
    }

    public function window(): ?string
    {
        return $this->current()->window();
    }

    /** @return array{dx: float, dy: float} */
    public function motion(): array
    {
        return $this->sum(fn (MouseContract $m): array => $m->motion());
    }

    /** @return array{dx: float, dy: float} */
    public function wheel(): array
    {
        return $this->sum(fn (MouseContract $m): array => $m->wheel());
    }

    public function button(MouseButton $button): ButtonState
    {
        return $this->current()->button($button);
    }

    public function isDown(MouseButton $button): bool
    {
        return $this->current()->isDown($button);
    }

    public function isPressed(MouseButton $button): bool
    {
        return $this->current()->isPressed($button);
    }

    public function wasReleased(MouseButton $button): bool
    {
        return $this->current()->wasReleased($button);
    }

    /** @return list<MouseContract> after the frame caught up */
    private function mice(): array
    {
        $this->frame->read();

        return ($this->mice)();
    }

    private function current(): MouseContract
    {
        $current = $this->resting;
        $latest = 0;

        foreach ($this->mice() as $mouse) {
            $report = $mouse instanceof Mouse ? $mouse->lastReport() : 0;

            if ($current === $this->resting || $report > $latest) {
                [$current, $latest] = [$mouse, $report];
            }
        }

        return $current;
    }

    /** @return array{dx: float, dy: float} */
    private function sum(Closure $delta): array
    {
        $dx = $dy = 0.0;

        foreach ($this->mice() as $mouse) {
            ['dx' => $x, 'dy' => $y] = $delta($mouse);
            $dx += $x;
            $dy += $y;
        }

        return ['dx' => $dx, 'dy' => $dy];
    }
}
