<?php

namespace Surface\NutsAndBolts;

/** What a gradient does past its ends: SVG spreadMethod, Skia's tile modes, Canvas 2D's gradients' clamp. */
enum GradientSpread: string
{
    /** The end colours go on. */
    case PAD = 'pad';

    /** The gradient starts over. */
    case REPEAT = 'repeat';

    /** The gradient runs back, then forward again. */
    case REFLECT = 'reflect';
}
