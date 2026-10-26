<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKScrollBar as PrimitiveContract;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\WindowException;

/**
 * The driver posts ScrollChanged from the native, right after nativeMoved(); nothing here posts.
 */
abstract class TKScrollBar extends TKPrimitive implements PrimitiveContract
{
    protected int $maximum = 0;

    protected int $page = 0;

    protected int $step = 1;

    protected int $value = 0;

    /**
     * @throws WindowException When the name is not valid.
     */
    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        protected bool $vertical,
    ) {
        parent::__construct($name, $window, $parent, $placement);
    }

    public function vertical(): bool
    {
        return $this->vertical;
    }

    public function setRange(int $maximum, int $page): static
    {
        $this->live();
        if ($maximum < 0 || $page < 0) {
            throw new WindowException('Scroll bar maximum and page must not be negative.');
        }
        $this->maximum = $maximum;
        $this->page = $page;
        $this->value = $this->clamp($this->value);
        $this->applyRange($maximum, $page, $this->value);

        return $this;
    }

    public function maximum(): int
    {
        return $this->maximum;
    }

    public function page(): int
    {
        return $this->page;
    }

    public function setStep(int $line): static
    {
        $this->live();
        if ($line < 1) {
            throw new WindowException('Scroll bar step must be at least 1.');
        }
        $this->step = $line;
        $this->applyStep($line);

        return $this;
    }

    public function step(): int
    {
        return $this->step;
    }

    public function setValue(int $value): static
    {
        $this->live();
        $this->value = $this->clamp($value);
        $this->applyValue($this->value);

        return $this;
    }

    public function value(): int
    {
        return $this->value;
    }

    /**
     * Engine callback: the user moved the bar; record the value clamped, post nothing.
     *
     * @param int $value
     * @return void
     */
    public function nativeMoved(int $value): void
    {
        $this->value = $this->clamp($value);
    }

    protected function clamp(int $value): int
    {
        return max(0, min(max(0, $this->maximum - $this->page), $value));
    }

    /**
     * Write the range and the value already clamped into it.
     *
     * @param int $maximum
     * @param int $page
     * @param int $value
     * @return void
     */
    abstract protected function applyRange(int $maximum, int $page, int $value): void;

    /**
     * @param int $line
     * @return void
     */
    abstract protected function applyStep(int $line): void;

    /**
     * @param int $value
     * @return void
     */
    abstract protected function applyValue(int $value): void;
}
