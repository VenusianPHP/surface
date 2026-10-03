<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKFixed as PrimitiveContract;
use Surface\Contracts\Windows\Primitives\TKPrimitive as Child;
use Surface\Contracts\Windows\WindowException;

/**
 * Children at pixel frames: at() sets the frame the next creation call takes.
 * The only container where pixels exist.
 */
abstract class TKFixed extends TKGroup implements PrimitiveContract
{
    /**
     * @var array<string, Placement> child name => current frame
     */
    protected array $frames = [];

    public function at(int $x, int $y, int $width, int $height): static
    {
        $this->live();
        $this->pending = Placement::frame($x, $y, $width, $height);

        return $this;
    }

    public function move(Child $child, int $x, int $y): static
    {
        $this->live();
        $this->own($child);
        $frame = $this->frames[$child->name()];
        $this->frames[$child->name()] = Placement::frame($x, $y, $frame->width, $frame->height);
        $this->applyMove($child, $x, $y);

        return $this;
    }

    public function resize(Child $child, int $width, int $height): static
    {
        $this->live();
        $this->own($child);
        $frame = $this->frames[$child->name()];
        $this->frames[$child->name()] = Placement::frame($frame->x, $frame->y, $width, $height);
        $this->applyResize($child, $width, $height);

        return $this;
    }

    public function frameOf(Child $child): Placement
    {
        $this->own($child);

        return $this->frames[$child->name()];
    }

    public function forgetChild(Child $child): void
    {
        parent::forgetChild($child);
        unset($this->frames[$child->name()]);
    }

    protected function defaultPlacement(): Placement
    {
        throw new WindowException("Fixed '{$this->path()}' needs at(x, y, width, height) before creating a child.");
    }

    /**
     * Record the child's creation frame before the engine inserts it, so insertNative() can read frameOf().
     *
     * @template T of Child
     * @param T $child
     * @return T
     */
    protected function adopt(Child $child): Child
    {
        $this->frames[$child->name()] = $child->placement();

        return parent::adopt($child);
    }

    /**
     * @param Child $child
     * @param int $x
     * @param int $y
     * @return void
     */
    abstract protected function applyMove(Child $child, int $x, int $y): void;

    /**
     * @param Child $child
     * @param int $width
     * @param int $height
     * @return void
     */
    abstract protected function applyResize(Child $child, int $width, int $height): void;
}
