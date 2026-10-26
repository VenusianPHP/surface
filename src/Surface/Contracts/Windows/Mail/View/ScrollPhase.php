<?php

namespace Surface\Contracts\Windows\Mail\View;

/**
 * Where a wheel event sits in a gesture. A wheel notch, or a device that reports no phases, is
 * NONE. A trackpad gesture is BEGAN, CHANGED..., ENDED with fingers down (a cancelled one ends
 * ENDED); its coast after a flick is MOMENTUM... then MOMENTUM_ENDED, whose deltas may be zero.
 */
enum ScrollPhase: string
{
    case NONE = 'none';
    case BEGAN = 'began';
    case CHANGED = 'changed';
    case ENDED = 'ended';
    case MOMENTUM = 'momentum';
    case MOMENTUM_ENDED = 'momentum-ended';
}
