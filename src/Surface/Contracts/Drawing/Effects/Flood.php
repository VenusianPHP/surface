<?php

namespace Surface\Contracts\Drawing\Effects;

use Surface\NutsAndBolts\Color;

/** SVG's feFlood: the colour, everywhere. */
final readonly class Flood extends FilterStep
{
    public function __construct(public Color $color, public ?string $result = null) {}

    public function reads(): array
    {
        return [];
    }

    public function named(): ?string
    {
        return $this->result;
    }
}
