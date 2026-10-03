<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKProgressBar as PrimitiveContract;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\WindowException;

abstract class TKProgressBar extends TKPrimitive implements PrimitiveContract
{
    /**
     * @throws WindowException When the name is not valid or the fraction is outside 0..1.
     */
    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        protected ?float $fraction,
    ) {
        self::guardFraction($fraction);
        parent::__construct($name, $window, $parent, $placement);
    }

    public function fraction(): ?float
    {
        return $this->fraction;
    }

    public function setFraction(?float $fraction): static
    {
        $this->live();
        self::guardFraction($fraction);
        $this->fraction = $fraction;
        $this->applyFraction($fraction);

        return $this;
    }

    /**
     * @param float|null $fraction
     * @return void
     * @throws WindowException When outside 0..1.
     */
    protected static function guardFraction(?float $fraction): void
    {
        if (! is_null($fraction) && (! is_finite($fraction) || $fraction < 0.0 || $fraction > 1.0)) {
            throw new WindowException("A progress fraction is 0..1 or null, got {$fraction}.");
        }
    }

    /**
     * @param float|null $fraction null = indeterminate
     * @return void
     */
    abstract protected function applyFraction(?float $fraction): void;
}
