<?php

namespace Surface\Windows\Primitives;

use DateTimeImmutable;
use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKDatepicker as PrimitiveContract;
use Surface\Contracts\Windows\ToolkitWindow;

/**
 * The driver posts DateChanged from the native signal, right after nativeDateChanged().
 */
abstract class TKDatepicker extends TKPrimitive implements PrimitiveContract
{
    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        protected ?DateTimeImmutable $date,
    ) {
        parent::__construct($name, $window, $parent, $placement);
    }

    public function date(): ?DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(?DateTimeImmutable $date): static
    {
        $this->live();
        $this->date = $date;
        $this->applyDate($date);

        return $this;
    }

    /**
     * Engine callback: record the user's date, post nothing, write nothing back.
     *
     * @param DateTimeImmutable $date
     * @return void
     */
    public function nativeDateChanged(DateTimeImmutable $date): void
    {
        $this->date = $date;
    }

    /**
     * @param DateTimeImmutable|null $date null = nothing chosen: the native shows its own default
     *        (today) and date() stays null until the user picks
     * @return void
     */
    abstract protected function applyDate(?DateTimeImmutable $date): void;
}
