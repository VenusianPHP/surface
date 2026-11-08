<?php

namespace Surface\Contracts\Drawing\Effects;

/** What a Field step writes. */
enum FieldKind
{
    /** Red: the signed distance to a rounded box, windowed; green and blue: the direction pointing out of it. */
    case ROUNDED_BOX;

    /** Red, green and blue: 255 inside a rounded box (distance below 0), 0 outside. */
    case INSIDE;

    /** Red, green and blue: how far down the surface the pixel is, windowed. */
    case RAMP;
}
