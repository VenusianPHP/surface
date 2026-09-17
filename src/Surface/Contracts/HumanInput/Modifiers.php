<?php

namespace Surface\Contracts\HumanInput;

/** The modifier keys held alongside a key or button event, all up by default. */
final readonly class Modifiers
{
    public function __construct(
        public bool $shift = false,
        public bool $ctrl = false,
        public bool $alt = false,
        public bool $meta = false,
    ) {
    }
}
