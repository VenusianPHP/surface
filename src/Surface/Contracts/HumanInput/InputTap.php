<?php

namespace Surface\Contracts\HumanInput;

/** Shown every native event a toolkit session handles, before the toolkit dispatches it. Records; never blocks and never consumes the event. */
interface InputTap
{
    public function see(object $native_event): void;
}
