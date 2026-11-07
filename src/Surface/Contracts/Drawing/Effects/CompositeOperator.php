<?php

namespace Surface\Contracts\Drawing\Effects;

/** How feComposite combines two images, on premultiplied colours. */
enum CompositeOperator: string
{
    case OVER = 'over';
    case IN = 'in';
    case OUT = 'out';
    case ATOP = 'atop';
    case XOR = 'xor';

    /** k1 × a × b + k2 × a + k3 × b + k4, per channel, clamped. */
    case ARITHMETIC = 'arithmetic';
}
