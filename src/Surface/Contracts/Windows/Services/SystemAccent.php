<?php

namespace Surface\Contracts\Windows\Services;

use Surface\NutsAndBolts\Color;

/** The colour the system draws its controls' emphasis in. */
interface SystemAccent
{
    /** The accent as an opaque colour; null when the system reports none. */
    public function color(): ?Color;
}
