<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\TKSpinner as PrimitiveContract;

abstract class TKSpinner extends TKPrimitive implements PrimitiveContract
{
    protected bool $spinning = false;

    public function start(): static
    {
        return $this->spin(true);
    }

    public function stop(): static
    {
        return $this->spin(false);
    }

    public function isSpinning(): bool
    {
        return $this->spinning;
    }

    /**
     * @param bool $spinning
     * @return $this
     */
    protected function spin(bool $spinning): static
    {
        $this->live();
        if ($this->spinning !== $spinning) {
            $this->spinning = $spinning;
            $this->applySpinning($spinning);
        }

        return $this;
    }

    /**
     * @param bool $spinning
     * @return void
     */
    abstract protected function applySpinning(bool $spinning): void;
}
