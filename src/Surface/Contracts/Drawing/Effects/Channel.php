<?php

namespace Surface\Contracts\Drawing\Effects;

/** A colour channel of a displacement map: SVG's xChannelSelector and yChannelSelector. */
enum Channel: string
{
    case R = 'r';
    case G = 'g';
    case B = 'b';
    case A = 'a';
}
