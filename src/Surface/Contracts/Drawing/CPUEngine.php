<?php

namespace Surface\Contracts\Drawing;

/** The CPU engines a sketch may name. Resolution is by container alias (`cpu.<engine>`), never by class. */
enum CPUEngine: string
{
    case DIRTY = 'dirty';
    case FULL = 'full';
    case EPAPER = 'epaper';
    case PAGED = 'paged';
    case NFRAMES = 'nframes';
}
